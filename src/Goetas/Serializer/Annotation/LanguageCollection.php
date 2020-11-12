<?php

namespace Goetas\Serializer\Annotation;

/**
 * @Annotation
 * @Target({"PROPERTY","METHOD"})
 */
final class LanguageCollection 
{
	/**
	 * @Required
	 * @var string
	 */
	public $entry = 'entry';
	/**
	 * @var string
	 */
	public $fallback = 'en';
	/**
	 * @var boolean
	 */
	public $any = false;
}
