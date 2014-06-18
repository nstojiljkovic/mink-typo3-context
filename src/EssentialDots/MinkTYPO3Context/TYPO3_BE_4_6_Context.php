<?php

namespace EssentialDots\MinkTYPO3Context;

use Behat\Behat\Context\ClosuredContextInterface,
		Behat\Behat\Context\TranslatedContextInterface,
		Behat\Behat\Context\BehatContext,
		Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
		Behat\Gherkin\Node\TableNode;

use Behat\MinkExtension\Context\MinkContext;

use  Behat\Mink\Exception\ElementNotFoundException,
		Behat\Mink\Exception\ExpectationException,
		Behat\Mink\Exception\ResponseTextException,
		Behat\Mink\Exception\ElementHtmlException,
		Behat\Mink\Exception\ElementTextException;

/**
 * Features context.
 */
class TYPO3_BE_4_6_Context extends \EssentialDots\Weasel\GeneralRawMinkContext {
	/**
	 * constructor
	 */
	public function __construct(array $parameters) {
		throw new PendingException();
	}
}
