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
class TYPO3BEContext extends \EssentialDots\Weasel\GeneralRawMinkContext {

	/**
	 * @var array;
	 */
	protected $parameters;

	/**
	 * constructor
	 */
	public function __construct(array $parameters) {
		$this->parameters = $parameters;
		$this->environment = 'backend';
	}

	/**
	 * Include t3atlib.js
	 */
	protected function withT3ATLib() {
		$hasT3ATLib = $this->getSession()->evaluateScript('return typeof window["T3ATLib"]!=="undefined"');

		if (!$hasT3ATLib) {
			$t3ATLibJS = file_get_contents(dirname(__FILE__).DIRECTORY_SEPARATOR.'JavaScript'.DIRECTORY_SEPARATOR.'t3atlib.js');
			$this->getSession()->executeScript($t3ATLibJS);
		}

		return $this->withSynJS();
	}

	/**
	 * @throws \Behat\Behat\Exception\PendingException
	 */
	protected function initializeSettings(\Behat\Mink\Session $session) {
		$this->isInitialized = TRUE;

		// always start from the login page
		$session->reset();
		$session->visit($this->locatePath('/typo3/index.php'));
		$typo3Version = $this->getTYPO3Version();

			// initialize settings
			// load general settings
		$generalSettingsFile = __DIR__.DIRECTORY_SEPARATOR.'Settings'.DIRECTORY_SEPARATOR.'settings.general.ini';
		if (@file_exists($generalSettingsFile) && @is_file($generalSettingsFile)) {
			$generalSettings = parse_ini_file($generalSettingsFile, true);
			if ($generalSettings === FALSE) {
				throw new \Exception("Wrong format of the settings.general.ini file.");
			}
		} else {
			$generalSettings = array();
		}
			// load specific settings for current TYPO3 version
		$settingsFile = __DIR__.DIRECTORY_SEPARATOR.'Settings'.DIRECTORY_SEPARATOR.'settings.'.$typo3Version.'.ini';
		if (@file_exists($settingsFile) && @is_file($settingsFile)) {
			$settings = parse_ini_file($settingsFile, true);
			if ($settings === FALSE) {
				throw new \Exception("Wrong format of the settings.$typo3Version.ini file.");
			}
		} else {
			$settings = array();
		}
			// merge arrays
		$this->settings = self::arrayMergeRecursiveOverrule($generalSettings, $settings);

		// NOTE: this won't work atm as ContextReader::readFromContext has already been executed
		// TODO: find a nice solution for this :)
		/*
		switch ($typo3Version) {
			case '4.6':
				$this->getMainContext()->useContext('TYPO346BEContext', new \EssentialDots\MinkExtension\Context\TYPO3_BE_4_6_Context($this->parameters));
				break;
			case '4.5':
			case '4.7':
			case '6.0':
			default:
				throw new PendingException();
				break;
		}
		*/
	}

	/**
	 * @return string
	 */
	public function getTYPO3Version() {
		return $this->withT3ATLib()->getSession()->evaluateScript('return window.T3ATLib.getTYPO3Version()');
	}

	/**
	 * @Given /^I am logged in on TYPO3 BE as "([^"]*)" with password "([^"]*)"$/
	 */
	public function iAmLoggedInOnTYPO3BEAsUsernameWithPassword($username, $password) {

			// go to backend url
		if ($this->getSession()->getCurrentUrl() != $this->locatePath($this->settings['paths']['login'])) {
			$this->getSession()->visit($this->locatePath($this->settings['paths']['login']));
		}

			// enter username
		$usernameField = $this->waitForElement(
			$this->settings['timeouts']['default'],
			$this->settings['selectors']['login_username']
		);
		$usernameField->setValue($username);

			// enter password
		$passwordField = $this->waitForElement(
			$this->settings['timeouts']['default'],
			$this->settings['selectors']['login_password']
		);
		$passwordField->setValue($password);

			// click on login button
		$submitButton = $this->waitForElement(
			$this->settings['timeouts']['default'],
			$this->settings['selectors']['login_submitButton']
		);
		$submitButton->press();

			// check if url changed
		$this->getSession()->wait(
			$this->settings['timeouts']['default'],
			$this->settings['conditions']['isBELoaded']
		);

		$this->assertSession()->addressEquals(
			$this->locatePath($this->settings['paths']['backend'])
		);
	}

	/**
	 * @Given /^I open "([^"]*)" module$/
	 */
	public function iOpenModule($module) {
		if (!$this->settings['modules'][$module]) {
			throw new PendingException("Locator for $module is not defined.");
		}

		$moduleLink = $this->waitForElement(
			$this->settings['timeouts']['default'],
			$this->settings['modules'][$module]
		);
		if ($moduleLink) {
			$moduleLink->click();

			$this->assertJSCondition(
				$this->settings['timeouts']['default'],
				$this->settings['modules']["is{$module}Selected"],
				"$module module has not been opened in {$this->settings['timeouts']['default']}ms."
			);
		} else {
			throw new ExpectationException("Element \"{$this->settings['modules'][$module]}\" not found.", $this->getSession());
		}
	}

	/**
	 * @Given /^I select page \[(\d+)\] "([^"]*)"$/
	 */
	public function iSelectPage($pageUid, $pageTitle) {

		$this->withT3ATLib()->assertJSCondition(
			$this->settings['timeouts']['default'],
			"window.T3ATLib.clickPageInPageTree($pageUid)",
			"Failed to select page [$pageUid] $pageTitle."
		);

		$this->withT3ATLib()->assertJSCondition(
			$this->settings['timeouts']['default'],
			"window.T3ATLib.isOnPage('$pageUid')",
			"Failed to open page [$pageUid] $pageTitle."
		);

		// Old version of the code
		/*
			// check if page tree is visible
		$pageTree = $this->waitForElement(
			$this->settings['timeouts']['default'],
			$this->settings['selectors']['be_pageTree']
		);

			// click on filter
		$pageTreeFilterToggle = $this->waitForElement(
			$this->settings['timeouts']['default'],
			$this->settings['selectors']['be_pageTreeFilterToggle']
		);
		$pageTreeFilterToggle->click();

			// insert page uid in the page tree filter
		$pageTreeFilterField = $this->waitForElement(
			$this->settings['timeouts']['default'],
			$this->settings['selectors']['be_pageTreeFilter']
		);
		$pageTreeFilterField->setValue($pageUid);

			// check and click if exist
		$this->withT3ATLib()->assertJSCondition(
			$this->settings['timeouts']['default'],
			"window.T3ATLib.selectPageInPageTree($pageUid)",
			"Failed to select page $pageUid."
		);

		// close filter
		$pageTreeFilterToggle->click();

		$this->waitForElementToDisappear(
			$this->settings['timeouts']['default'],
			$this->settings['selectors']['loadMask']
		);
		*/
	}


	/**
	* @Given /^I edit page \[(\d+)\] "([^"]*)"$/
	*
	*/
	public function iEditPage($pageId, $pageName) {
		//open edit page page
		$this->withT3ATLib()->getSession()->executeScript('window.T3ATLib.editPage()');

		// check if everything went fine
		$this->withT3ATLib()->assertJSCondition(
			$this->settings['timeouts']['default'],
			"window.T3ATLib.isEditingPage('$pageName', '$pageId')",
			"Failed to edit record [$pageId] $pageName."
		);
	}


	/**
	 * @Given /^I edit record \[(\d+)\] "([^"]*)"$/
	 *
	 * @param int $uid
	 * @param string $tableLabel
	 *
	 */
	public function iEditRecord($uid, $tableLabel) {
		// get exact table name
		$tableName = $this->withT3ATLib()->getSession()->evaluateScript('return window.T3ATLib.getTableName("'.$tableLabel.'")');
		if (!$tableName) {
			throw new ExpectationException("Cannot determine exact table name for \"$tableLabel\"", $this->getSession());
		}

		// open edit record page
		$this->withT3ATLib()->getSession()->executeScript('window.T3ATLib.editRecord("'.$tableName.'", '.intval($uid).')');

		// check if everything went fine
		$this->withT3ATLib()->assertJSCondition(
			$this->settings['timeouts']['default'],
			"window.T3ATLib.isEditingRecord('$tableLabel', '$uid')",
			"Failed to edit record [$uid] $tableLabel."
		);
	}


	/**
	 * @Given /^I edit record "([^"]*)" where (.*)$/
	 *
	 * @param int $uid
	 * @param string $tableLabel
	 *
	 */
	public function iEditRecordWhere($tableLabel, $whereClause) {
		// get exact table name
		$tableName = $this->withT3ATLib()->getSession()->evaluateScript('return window.T3ATLib.getTableName("'.$tableLabel.'")');
		if (!$tableName) {
			throw new ExpectationException("Cannot determine exact table name for \"$tableLabel\"", $this->getSession());
		}

		$matches = array();
		if (preg_match('/(.*) on page \[(\d+)\] "([^"]*)"/msU', $whereClause, $matches)) {
			$whereClause = $matches[1];
			$pid = $matches[2];
		}

		// open edit record page
		$this->withT3ATLib()->getSession()->executeScript('window.T3ATLib.editRecord("'.$tableName.'", null, '.json_encode($whereClause).', '.intval($pid).')');

		// check if everything went fine
		$this->withT3ATLib()->assertJSCondition(
			$this->settings['timeouts']['default'],
			"window.T3ATLib.isEditingRecord('$tableLabel')",
			"Failed to edit record $tableLabel."
		);
	}


	/**
	 * @Given /^I select tab "([^"]*)"$/
	 */
	public function iSelectTab($tabName) {

		$tab = $this->withT3ATLib()->getSession()->evaluateScript('return window.T3ATLib.clickOnTab("'.$tabName.'")');
		if (!$tab) {
			throw new ExpectationException("Cannot determine exact tab name for \"$tabName\"", $this->getSession());
		}

		// check if tab selected
		$this->withT3ATLib()->assertJSCondition(
			$this->settings['timeouts']['default'],
			"window.T3ATLib.isTabSelected('$tabName')",
			"Failed to select tab $tabName."
		);
	}


	/**
	 * @Given /^I set\s*[o|][n|]\s*(.*) to "([^"]*)"$/
	 */
	public function iSet($selector, $value) {
		list($selectorArr) = self::str_getcsv($selector);

		$this->withT3ATLib()->assertJSCondition(
			$this->settings['timeouts']['default'],
			'window.T3ATLib.setField('.json_encode($selectorArr).', '.json_encode($value).')===true',
			"Could not set $selector to $value."
		);
	}

	/**
	 * @Given /^I click\s*[o|][n|]\s*(.*),\s*link "([^"]*)"$/
	 */
	public function iClick($selector, $link) {
		list($selectorArr) = self::str_getcsv($selector);
		$success = $this->withT3ATLib()->getSession()->evaluateScript('return window.T3ATLib.clickLink('.json_encode($selectorArr).', '.json_encode($link).')');
		if (!$success) {
			throw new ExpectationException("Could not click on link $link, on $selector.", $this->getSession());
		}
	}

	/**
	 * @Given /^I save record$/
	 */
	public function iSaveRecord() {
		$success = $this->withT3ATLib()->getSession()->evaluateScript('return window.T3ATLib.saveRecord()');
		if (!$success) {
			throw new ExpectationException("Could not save record.", $this->getSession());
		}
		$this->withT3ATLib()->assertJSCondition(
			$this->settings['timeouts']['default'],
			'window.T3ATLib.hasFinishedSaving()',
			"Could not verify that the record is saved."
		);
	}

	/**
	 * @Given /^I clear all cache$/
	 */
	public function iClearAllCache() {
		// go to backend url - this is needed in order to overcome the weird about:blank webkit capybara bug
		if ($this->getSession()->getCurrentUrl() != $this->locatePath($this->settings['paths']['backend'])) {
			$this->getSession()->visit($this->locatePath($this->settings['paths']['backend']));
		}

		// click on clear toolbar icon
		$clearToolbarIcon = $this->waitForElement(
			$this->settings['timeouts']['default'],
			$this->settings['selectors']['clear_toolbar']
		);
		$clearToolbarIcon->press();

		// click on clear all cache button
		$clearCache = $this->waitForElement(
			$this->settings['timeouts']['default'],
			$this->settings['selectors']['clear_all_caches']
		);
		$clearCache->press();

	}

}
