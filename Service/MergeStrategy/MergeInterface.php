<?php

namespace nediam\PhraseAppBundle\Service\MergeStrategy;

interface MergeInterface
{
    /**
     * @param $format
     *
     * @return bool
     */
    public function canMerge($format);

    /**
     * Megres array of jsons
     *
     * @param array $content
     *
     * @return string
     */
    public function merge(array $content);
}
