<?php
/**
 * Author: grzegorz
 * Date: 14.01.16 20:39
 */

namespace nediam\PhraseAppBundle\Events;

use Symfony\Contracts\EventDispatcher\Event;

class PostDownloadEvent extends Event
{
    /**
     * @var array
     */
    private $tempData = [];

    /**
     * @var null|string
     */
    private $finalFilePath = null;

    /**
     * @var string
     */
    private $locale;

    /**
     * @var string
     */
    private $catalogue;

    /**
     * PostDownloadEvent constructor.
     *
     * @param array  $tempData
     * @param string $locale
     * @param string $catalogue
     */
    public function __construct(array $tempData, $locale, $catalogue)
    {
        $this->tempData  = $tempData;
        $this->locale    = $locale;
        $this->catalogue = $catalogue;
    }

    /**
     * @return array
     */
    public function getTempData()
    {
        return $this->tempData;
    }

    /**
     * @param array $tempData
     *
     * @return $this
     */
    public function setTempData(array $tempData)
    {
        $this->tempData = $tempData;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getFinalFilePath()
    {
        return $this->finalFilePath;
    }

    /**
     * @param null|string $finalFilePath
     *
     * @return $this
     */
    public function setFinalFilePath($finalFilePath = null)
    {
        $this->finalFilePath = $finalFilePath;

        return $this;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param string $locale
     *
     * @return $this
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return string
     */
    public function getCatalogue()
    {
        return $this->catalogue;
    }

    /**
     * @param string $catalogue
     *
     * @return $this
     */
    public function setCatalogue($catalogue)
    {
        $this->catalogue = $catalogue;

        return $this;
    }


}
