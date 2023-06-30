<?php
// Определение пути к файлу плагина

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Table\Table;




class plgContentJsonLdProduct extends CMSPlugin
{
    public function onContentPrepareForm($form, $data)
    {
        if ($form->getName() !== 'com_content.article') {
            return;
        }

        JForm::addFormPath(__DIR__ . '/forms');
        $form->loadFile('article', true);

        $articleId = JFactory::getApplication()->input->getInt('id');
        $article = JTable::getInstance('content');
        $article->load($articleId);
        $content = $article->get('introtext') . $article->get('fulltext');

        $jsonLdData = $this->getJsonLdData($content);

        if ($jsonLdData) {
            $form->setValue('lowPrice', null, $jsonLdData->offers->lowPrice);
            $form->setValue('highPrice', null, $jsonLdData->offers->highPrice);
            $form->setValue('ratingValue', null, $jsonLdData->aggregateRating->ratingValue);
            $form->setValue('bestRating', null, $jsonLdData->aggregateRating->bestRating);
            $form->setValue('ratingCount', null, $jsonLdData->aggregateRating->ratingCount);
        }

        $enableJsonLd = $this->hasJsonLdProduct($content) ? 1 : 0;
        $form->setValue('enableJsonLd', null, $enableJsonLd);



    }
    public function onContentBeforeSave($context, $article, $isNew)
    {
        if ($context !== 'com_content.article') {
            return;
        }

        $application = JFactory::getApplication();
		$data = $application->input->post->get('jform', array(), 'array');

        //JLog::add('Data: ' . var_export($data, true), JLog::ERROR, 'jsonldproduct');

        $articleId = $data['id'];
        $articleText = $data['articletext'];
        // $article->load($articleId);
        $introText = $data['metadesc'];

        // $attribs = $article->attribs;
        $articleTitle = $data['title'];
        $attribsArray = $data['attribs'];
        $introImage = $data['images']['image_intro'];

        $articleIntroImage = isset($introImage) ? JURI::root() . $introImage : '';
        $lowPrice = isset($attribsArray['lowPrice']) ? $attribsArray['lowPrice'] : '';
        $highPrice = isset($attribsArray['highPrice']) ? $attribsArray['highPrice'] : '';
        $ratingValue = isset($attribsArray['ratingValue']) ? $attribsArray['ratingValue'] : '';
        $bestRating = isset($attribsArray['bestRating']) ? $attribsArray['bestRating'] : '';
        $ratingCount = isset($attribsArray['ratingCount']) ? $attribsArray['ratingCount'] : '';
        $enableJsonLd = isset($attribsArray['enableJsonLd']) ? $attribsArray['enableJsonLd'] : '';

        if ($enableJsonLd == 1) {
            $article->fulltext = $this->updateJsonLdData($article->fulltext, $articleTitle, $introText, $articleIntroImage,  $lowPrice, $highPrice, $ratingValue, $bestRating, $ratingCount, $enableJsonLd);
        } else {
            $article->fulltext = $this->removeJsonLdData($article->fulltext);
        }
        //JLog::add('Data: ' . var_export($article->fulltext, true), JLog::ERROR, 'jsonldproduct');

    }

    private function getJsonLdData($content)
    {
        $pattern = '/"@type"\s*:\s*"Product"(.*?})\s*}/s';
        preg_match($pattern, $content, $matches);

        if (!empty($matches[1])) {
            $jsonLdData = json_decode('{' . $matches[1] . '}');
            return $jsonLdData;
        }

        return null;
    }

    private function updateJsonLdData($content, $articleTitle, $introText, $articleIntroImage, $lowPrice, $highPrice, $ratingValue, $bestRating, $ratingCount, $enableJsonLd)
    {
        if ($enableJsonLd != 1) {
            return $content;
        }

        $content = preg_replace('/<script type="application\/ld\+json">\s*{.*?"@type"\s*:\s*"Product".*?}\s*<\/script>\s*/is', '', $content);

        $jsonLd = new stdClass();
        $jsonLd->{'@context'} = 'https://schema.org/';
        $jsonLd->{'@type'} = 'Product';
        $jsonLd->name = $articleTitle;
        $jsonLd->image = $articleIntroImage;
        $jsonLd->description = $introText;

        $jsonLd->brand = new stdClass();
        $jsonLd->brand->{'@type'} = 'Brand';
        $jsonLd->brand->name = 'Telezon';

        $jsonLd->offers = new stdClass();
        $jsonLd->offers->{'@type'} = 'AggregateOffer';
        $jsonLd->offers->url = '';
        $jsonLd->offers->priceCurrency = 'RUB';
        $jsonLd->offers->lowPrice = $lowPrice;
        $jsonLd->offers->highPrice = $highPrice;
        $jsonLd->offers->priceValidUntil = date('Y-m-d', strtotime('+1 year'));
        $jsonLd->offers->availability= "https://schema.org/InStock";
        $jsonLd->offers->itemCondition = "https://schema.org/NewCondition";

        $jsonLd->aggregateRating = new stdClass();
        $jsonLd->aggregateRating->{'@type'} = 'AggregateRating';
        $jsonLd->aggregateRating->ratingValue = $ratingValue;
        $jsonLd->aggregateRating->bestRating = $bestRating;
        $jsonLd->aggregateRating->worstRating = '1';
        $jsonLd->aggregateRating->ratingCount = $ratingCount;


        $jsonLdString = json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $scriptTag = '<script type="application/ld+json">' . $jsonLdString . '</script>';
        $content .= $scriptTag;

        return $content;
    }

    private function removeJsonLdData($content)
    {
        $pattern = '/<script\s+type="application\/ld\+json">(.*?)@type"\s*:\s*"Product"(.*?)\s*}\s*<\/script>/is';
        $content = preg_replace($pattern, '', $content);

        $content = trim($content);

        return $content;
    }

    private function hasJsonLdProduct($content)
    {
        return preg_match('/<script type="application\/ld\+json">\s*{.*?"@type"\s*:\s*"Product".*?}\s*<\/script>\s*/is', $content) === 1;
    }
}