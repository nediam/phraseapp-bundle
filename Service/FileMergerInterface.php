<?php
declare(strict_types=1);

namespace nediam\PhraseAppBundle\Service;

interface FileMergerInterface
{
	/**
	 * @param $content
	 * @param $format
	 *
	 * @return string
	 */
	public function merge($content, $format);
}
