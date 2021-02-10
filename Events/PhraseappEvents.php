<?php

namespace nediam\PhraseAppBundle\Events;

final class PhraseappEvents
{
	/**
	 * @deprecated use {@see PostDownloadEvent::class} directly
	 *
	 * The 'phraseapp.post_download event is thrown each time when all files are downloaded and wait for final save
	 *
	 * The event listener receives an
	 * nediam\PhraseAppBundle\Events\PostDownloadEvent instance.
	 *
	 * @var string
	 */
	const POST_DOWNLOAD = 'phraseapp.post_download';
}
