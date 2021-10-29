<?php
/**
 * JComments - Joomla Comment System
 *
 * @version 4.0
 * @package JComments
 * @author Sergey M. Litvinov (smart@joomlatune.ru) & exstreme (info@protectyoursite.ru) & Vladimir Globulopolis
 * @copyright (C) 2006-2022 by Sergey M. Litvinov (http://www.joomlatune.ru) & exstreme (https://protectyoursite.ru) & Vladimir Globulopolis (https://xn--80aeqbhthr9b.com/ru/)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\String\StringHelper;

error_reporting(E_ALL);
ini_set('error_reporting', E_ALL);

ob_start();

// Regular expression for links
DEFINE('_JC_REGEXP_LINK', '#(^|\s|\>|\()((http://|https://|news://|ftp://|www.)\w+[^\s\<\>\"\'\)]+)#iu');
DEFINE('_JC_REGEXP_EMAIL', '#([\w\.\-]+)@(\w+[\w\.\-]*\.\w{2,6})#iu');
DEFINE('_JC_REGEXP_EMAIL2', '#^([_a-z0-9-]+)(\.[_a-z0-9-]+)*@([a-z0-9-]+)(\.[a-z0-9-]+)*(\.[a-z]{2,6})$#iu');

require_once(JPATH_ROOT . '/components/com_jcomments/jcomments.class.php');
require_once(JPATH_ROOT . '/components/com_jcomments/models/jcomments.php');
ob_end_clean();

$app = JFactory::getApplication();

$jc_task = $app->input->get('task', '');

switch (trim($jc_task)) {
	case 'captcha':
		$config = ComponentHelper::getParams('com_jcomments');
		$captchaEngine = $config->get('captcha_engine', 'kcaptcha');
		if ($captchaEngine == 'kcaptcha' || (int) $config->get('enable_plugins') == 0) {
			require_once(JPATH_ROOT . '/components/com_jcomments/jcomments.captcha.php');
			JCommentsCaptcha::image();
		} else {
			if ((int) $config->get('enable_plugins') == 1) {
				JCommentsEvent::trigger('onJCommentsCaptchaImage');
			}
		}
		break;
	case 'rss':
		require_once(JPATH_ROOT . '/components/com_jcomments/jcomments.feed.php');
		JCommentsFeed::display('object');
		break;
	case 'rss_full':
		require_once(JPATH_ROOT . '/components/com_jcomments/jcomments.feed.php');
		JCommentsFeed::display('all');
		break;
	case 'rss_user':
		require_once(JPATH_ROOT . '/components/com_jcomments/jcomments.feed.php');
		JCommentsFeed::display('user');
		break;
	case 'unsubscribe':
		JComments::unsubscribe();
		break;
	case 'cmd':
		JComments::executeCmd();
		break;
	case 'notifications-cron':
		$limit = $app->input->getInt('limit', 10);
		$secret = trim($app->input->get('secret', ''));

		if ($secret == $app->getCfg('secret')) {
			JCommentsNotification::send($limit);
		}
		break;

	case 'refreshObjectsAjax':
		require_once(JPATH_ROOT . '/components/com_jcomments/jcomments.ajax.php');
		JCommentsAJAX::refreshObjectsAjax();
		exit;

	default:
		$jc_option = $app->input->get('option', '');
		$jc_ajax = $app->input->get('jtxf', '');

		if ($jc_option == 'com_jcomments' && $jc_ajax == '' && !$app->isClient('administrator')) {

			$_Itemid = $app->input->getInt('Itemid');
			$_tmpl = $app->input->get('tmpl');

			if ($_Itemid !== 0 && $_tmpl !== 'component') {
				// TODO What's this?
				// $params = JComponentHelper::getParams('com_jcomments');
				$params = $app->getParams();

				$object_group = $params->get('object_group');
				$object_group = JCommentsSecurity::clearObjectGroup($object_group);
				
				$object_id = (int)$params->get('object_id', 0);

				if ($object_id != 0 && $object_group != '') {

					if ($params->get('language_suffix') != '') {
						JComments::loadAlternateLanguage($params->get('language_suffix'));
					}

					$keywords = $params->get('menu-meta_keywords');
					$description = $params->get('menu-meta_description');
					$title = $params->get('page_title');

					$document = JFactory::getDocument();

					if (empty($title)) {
						$title = $app->getCfg('sitename');
					} elseif ($app->getCfg('sitename_pagetitles', 0) == 1) {
						$title = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $title);
					} elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
						$title = JText::sprintf('JPAGETITLE', $title, $app->getCfg('sitename'));
					}

					$document->setTitle($title);

					if ($keywords) {
						$document->setMetaData('keywords', $keywords);
					}

					if ($description) {
						$document->setDescription($description);
					}

					echo JComments::show($object_id, $object_group);
				} else {
					JFactory::getApplication()->redirect(JRoute::_(rtrim(JURI::base(), '/')));
				}
			} else {
				JFactory::getApplication()->redirect(JRoute::_(rtrim(JURI::base(), '/')));
			}
		}
		break;
}

if (isset($_REQUEST['jtxf'])) {
	require_once(JPATH_ROOT . '/components/com_jcomments/jcomments.ajax.php');

	JComments::loadAlternateLanguage();

	$jtx = new JoomlaTuneAjax();
	$jtx->setCharEncoding('utf-8');
	$jtx->registerFunction(array('JCommentsAddComment', 'JCommentsAJAX', 'addComment'));
	$jtx->registerFunction(array('JCommentsDeleteComment', 'JCommentsAJAX', 'deleteComment'));
	$jtx->registerFunction(array('JCommentsEditComment', 'JCommentsAJAX', 'editComment'));
	$jtx->registerFunction(array('JCommentsCancelComment', 'JCommentsAJAX', 'cancelComment'));
	$jtx->registerFunction(array('JCommentsSaveComment', 'JCommentsAJAX', 'saveComment'));
	$jtx->registerFunction(array('JCommentsPublishComment', 'JCommentsAJAX', 'publishComment'));
	$jtx->registerFunction(array('JCommentsQuoteComment', 'JCommentsAJAX', 'quoteComment'));
	$jtx->registerFunction(array('JCommentsShowPage', 'JCommentsAJAX', 'showPage'));
	$jtx->registerFunction(array('JCommentsShowComment', 'JCommentsAJAX', 'showComment'));
	$jtx->registerFunction(array('JCommentsJump2email', 'JCommentsAJAX', 'jump2email'));
	$jtx->registerFunction(array('JCommentsShowForm', 'JCommentsAJAX', 'showForm'));
	$jtx->registerFunction(array('JCommentsVoteComment', 'JCommentsAJAX', 'voteComment'));
	$jtx->registerFunction(array('JCommentsShowReportForm', 'JCommentsAJAX', 'showReportForm'));
	$jtx->registerFunction(array('JCommentsReportComment', 'JCommentsAJAX', 'reportComment'));
	$jtx->registerFunction(array('JCommentsSubscribe', 'JCommentsAJAX', 'subscribeUser'));
	$jtx->registerFunction(array('JCommentsUnsubscribe', 'JCommentsAJAX', 'unsubscribeUser'));
	$jtx->registerFunction(array('JCommentsBanIP', 'JCommentsAJAX', 'BanIP'));
	$jtx->processRequests();
}

/**
 * Frontend event handler
 */
class JComments
{
	/*
	 * The main function that displays comments list & form (if needed)
	 *
	 * @return string
	 */
	public static function show($object_id, $object_group = 'com_content', $object_title = '')
	{
		// only one copy of JComments per page is allowed
		if (defined('JCOMMENTS_SHOW')) {
			return '';
		}

		$app = JFactory::getApplication('site');
		$object_group = JCommentsSecurity::clearObjectGroup($object_group);

		if ($object_group == '' || !isset($object_id) || $object_id == '') {
			return '';
		}

		$object_id = (int)$object_id;
		$object_title = trim($object_title);

		$acl = JCommentsFactory::getACL();
		$config = ComponentHelper::getParams('com_jcomments');
		$document = JFactory::getDocument();
		$user = Factory::getApplication()->getIdentity();

		$tmpl = JCommentsFactory::getTemplate($object_id, $object_group);
		$tmpl->load('tpl_index');

		if (!defined('JCOMMENTS_CSS')) {
			include_once(JPATH_ROOT . '/components/com_jcomments/helpers/system.php');
			if ($app->isClient('administrator')) {
				$tmpl->addVar('tpl_index', 'comments-css', 1);
			} else {
				$document->addStyleSheet(JCommentsSystem::getCSS());
				$language = JFactory::getLanguage();
				if ($language->isRTL()) {
					$rtlCSS = JCommentsSystem::getCSS(true);
					if ($rtlCSS != '') {
						$document->addStyleSheet($rtlCSS);
					}
				}
			}
		}

		if (!defined('JCOMMENTS_JS')) {
			include_once(JPATH_ROOT . '/components/com_jcomments/helpers/system.php');
			$document->addScript(JCommentsSystem::getCoreJS());
			define('JCOMMENTS_JS', 1);

			if (!defined('JOOMLATUNE_AJAX_JS')) {
				$document->addScript(JCommentsSystem::getAjaxJS());
				define('JOOMLATUNE_AJAX_JS', 1);
			}
		}

		$commentsCount = JComments::getCommentsCount($object_id, $object_group);
		$commentsPerObject = (int) $config->get('max_comments_per_object');
		$showForm = ((int) $config->get('form_show') == 1) || ((int) $config->get('form_show') == 2 && $commentsCount == 0);

		if ($commentsPerObject != 0 && $commentsCount >= $commentsPerObject) {
			$config->set('comments_locked', 1);
		}

		if ((int) $config->get('comments_locked', 0) == 1) {
			$config->set('enable_rss', 0);
			$tmpl->addVar('tpl_index', 'comments-form-locked', 1);
			$acl->setCommentsLocked(true);
		}

		$tmpl->addVar('tpl_index', 'comments-form-captcha', $user->authorise('comment.captcha', 'com_jcomments'));
		$tmpl->addVar('tpl_index', 'comments-form-link', $showForm ? 0 : 1);

		if ((int) $config->get('enable_rss') == 1) {
			if ($document->getType() == 'html') {
				$link = JCommentsFactory::getLink('rss', $object_id, $object_group);
				$title = htmlspecialchars($object_title, ENT_COMPAT, 'UTF-8');
				$attribs = array('type' => 'application/rss+xml', 'title' => $title);
				$document->addHeadLink($link, 'alternate', 'rel', $attribs);
			}
		}

		$cacheEnabled = intval($app->getCfg('caching')) != 0;

		if ($cacheEnabled == 0) {
			$jrecache = JPATH_ROOT . '/components/com_jrecache/jrecache.config.php';
			if (is_file($jrecache)) {
				$cfg = new _JRECache_Config();
				$cacheEnabled = $cacheEnabled && $cfg->enable_cache;
			}
		}

		$load_cached_comments = intval((int) $config->get('load_cached_comments', 0) && $commentsCount > 0);

		if ($cacheEnabled) {
			$tmpl->addVar('tpl_index', 'comments-anticache', 1);
		}

		if (!$cacheEnabled || $load_cached_comments === 1) {
			if ($config->get('template_view') == 'tree') {
				$tmpl->addVar('tpl_index', 'comments-list',
					$commentsCount > 0 ? JComments::getCommentsTree($object_id, $object_group) : '');
			} else {
				$tmpl->addVar('tpl_index', 'comments-list',
					$commentsCount > 0 ? JComments::getCommentsList($object_id, $object_group) : '');
			}
		}

		$needScrollToComment = ($cacheEnabled || ((int) $config->get('comments_per_page') > 0)) && $commentsCount > 0;
		$tmpl->addVar('tpl_index', 'comments-gotocomment', (int)$needScrollToComment);
		$tmpl->addVar('tpl_index', 'comments-form', JComments::getCommentsForm($object_id, $object_group, $showForm));
		$tmpl->addVar('tpl_index', 'comments-form-position', (int) $config->get('form_position'));

		$result = $tmpl->renderTemplate('tpl_index');
		$tmpl->freeAllTemplates();

		// send notifications
		srand((float)microtime() * 10000000);
		$randValue = intval(rand(0, 100));

		if ($randValue <= 30) {
			JCommentsNotification::send();
		}

		define('JCOMMENTS_SHOW', 1);

		return $result;
	}

	public static function loadAlternateLanguage($languageSuffix = '')
	{
		if ($languageSuffix == '') {
			$languageSuffix = JFactory::getApplication()->input->get('lsfx', '');
		}

		if ($languageSuffix != '') {
			$config = ComponentHelper::getParams('com_jcomments');
			$config->set('lsfx', $languageSuffix);

			$language = JFactory::getLanguage();
			$language->load('com_jcomments.' . $languageSuffix, JPATH_SITE);
		}
	}

	public static function getCommentsForm($object_id, $object_group, $showForm = true)
	{
		$object_id = (int)$object_id;
		$object_group = JCommentsSecurity::clearObjectGroup($object_group);

		$tmpl = JCommentsFactory::getTemplate($object_id, $object_group);
		$tmpl->load('tpl_form');

		$lang = Factory::getApplication()->getLanguage();
		$user = JFactory::getUser();
		$acl = JCommentsFactory::getACL();
		$config = ComponentHelper::getParams('com_jcomments');

		if ($user->authorise('comment.comment', 'com_jcomments')) {
			if ((int) $config->get('comments_locked') == 1) {
				$message = JCommentsText::getMessagesBasedOnLanguage($config->get('messages_fields'), 'message_locked', $lang->getTag());

				if ($message != '') {
					$message = stripslashes($message);
					if ($message == strip_tags($message)) {
						$message = nl2br($message);
					}
				} else {
					$message = Text::_('ERROR_CANT_COMMENT');
				}

				$tmpl->addVar('tpl_form', 'comments-form-message', 1);
				$tmpl->addVar('tpl_form', 'comments-form-message-header', JText::_('FORM_HEADER'));
				$tmpl->addVar('tpl_form', 'comments-form-message-text', $message);
				$result = $tmpl->renderTemplate('tpl_form');

				return $result;
			}

			if ($user->authorise('comment.captcha', 'com_jcomments') == 1) {
				$captchaEngine = $config->get('captcha_engine', 'kcaptcha');
				if ($captchaEngine != 'kcaptcha') {
					JCommentsEvent::trigger('onJCommentsCaptchaJavaScript');
				}
			}

			if (!$showForm) {
				$tmpl->addVar('tpl_form', 'comments-form-link', 1);
				$result = $tmpl->renderTemplate('tpl_form');

				return $result;
			} else {
				if ((int) $config->get('form_show') != 1) {
					$tmpl->addVar('tpl_form', 'comments-form-ajax', 1);
				}
			}

			if ((int) $config->get('enable_plugins') == 1) {

				$htmlBeforeForm = JCommentsEvent::trigger('onJCommentsFormBeforeDisplay', array($object_id, $object_group));
				$htmlAfterForm = JCommentsEvent::trigger('onJCommentsFormAfterDisplay', array($object_id, $object_group));

				$htmlBeforeForm = implode("\n", $htmlBeforeForm);
				$htmlAfterForm = implode("\n", $htmlAfterForm);

				// show HTML before or after form element
				$tmpl->addVar('tpl_form', 'comments-html-before-form', $htmlBeforeForm);
				$tmpl->addVar('tpl_form', 'comments-html-after-form', $htmlAfterForm);

				// backward compatibility
				$tmpl->addVar('tpl_form', 'comments-form-html-before', $htmlBeforeForm);
				$tmpl->addVar('tpl_form', 'comments-form-html-after', $htmlAfterForm);

				// prepend or append HTML code inside form element
				$htmlFormPrepend = JCommentsEvent::trigger('onJCommentsFormPrepend', array($object_id, $object_group));
				$htmlFormAppend = JCommentsEvent::trigger('onJCommentsFormAppend', array($object_id, $object_group));

				$tmpl->addVar('tpl_form', 'comments-form-html-prepend', $htmlFormPrepend);
				$tmpl->addVar('tpl_form', 'comments-form-html-append', $htmlFormAppend);
			}
			
			$show_checkbox_terms_of_use = $config->get('show_checkbox_terms_of_use');
			if (($show_checkbox_terms_of_use == 1) && ($user->authorise('comment.terms_of_use', 'com_jcomments') == 1))
			{
 				$tmpl->addVar('tpl_form', 'var_show_checkbox_terms_of_use', 1);
			}

			$policy = JCommentsText::getMessagesBasedOnLanguage($config->get('messages_fields'), 'message_policy_post', $lang->getTag());
			if (($policy != '') && ($user->authorise('show_policy', 'com_jcomments'))) {
				$policy = stripslashes($policy);
				if ($policy == strip_tags($policy)) {
					$policy = nl2br($policy);
				}
				$tmpl->addVar('tpl_form', 'comments-form-policy', 1);
				$tmpl->addVar('tpl_form', 'comments-policy', $policy);
			}

			if ($user->id) {
				$currentUser = JFactory::getUser($user->id);
				$user->name = $currentUser->name;
				unset($currentUser);
			}

			$tmpl->addObject('tpl_form', 'user', $user);

			if ((int) $config->get('enable_smilies') == 1) {
				$tmpl->addVar('tpl_form', 'comment-form-smiles', JCommentsFactory::getSmilies()->getList());
			}

			$bbcode = JCommentsFactory::getBBCode();

			if ($bbcode->enabled()) {
				$tmpl->addVar('tpl_form', 'comments-form-bbcode', 1);
				foreach ($bbcode->getCodes() as $code) {
					$tmpl->addVar('tpl_form', 'comments-form-bbcode-' . $code, $bbcode->canUse($code));
				}
			}

			if ((int) $config->get('enable_custom_bbcode')) {
				$tmpl->addVar('tpl_form', 'comments-form-custombbcodes', JCommentsFactory::getCustomBBCode()->getList());
			}

			$username_maxlength = (int) $config->get('username_maxlength');
			if ($username_maxlength <= 0 || $username_maxlength > 255) {
				$username_maxlength = 255;
			}
			$tmpl->addVar('tpl_form', 'comment-name-maxlength', $username_maxlength);

			if (((int) $config->get('show_commentlength') == 1)
				&& ($user->authorise('comment.length_check', 'com_jcomments'))
			) {
				$tmpl->addVar('tpl_form', 'comments-form-showlength-counter', 1);
				$tmpl->addVar('tpl_form', 'comment-maxlength', (int) $config->get('comment_maxlength'));
			} else {
				$tmpl->addVar('tpl_form', 'comment-maxlength', 0);
			}

			if ($user->authorise('comment.captcha', 'com_jcomments') == 1) {
				$tmpl->addVar('tpl_form', 'comments-form-captcha', 1);

				$captchaEngine = $config->get('captcha_engine', 'kcaptcha');
				if ( ($captchaEngine == 'kcaptcha') || ($captchaEngine == 'recaptcha') || ($captchaEngine == 'recaptcha_invisible') )
				{
					$tmpl->addVar('tpl_form', 'comments-form-captcha-html', $captchaEngine);
				}
				else
				{
					$captchaHTML = JCommentsEvent::trigger('onJCommentsCaptchaDisplay');
					$tmpl->addVar('tpl_form', 'comments-form-captcha-html', implode("\n", $captchaHTML));
				}
			}

			$canSubscribe = $user->authorise('comment.subscribe', 'com_jcomments');

			if ($user->id && $canSubscribe) {
				require_once(JPATH_ROOT . '/components/com_jcomments/jcomments.subscription.php');
				$manager = JCommentsSubscriptionManager::getInstance();
				$canSubscribe = $canSubscribe && (!$manager->isSubscribed($object_id, $object_group, $user->id));
			}

			$tmpl->addVar('tpl_form', 'comments-form-subscribe', (int)$canSubscribe);
			$tmpl->addVar('tpl_form', 'comments-form-email-required', 0);

			switch ((int) $config->get('author_name')) {
				case 2:
					if (!$user->id) {
						$tmpl->addVar('tpl_form', 'comments-form-user-name-required', 1);
						$tmpl->addVar('tpl_form', 'comments-form-user-name', 1);
					} else {
						$tmpl->addVar('tpl_form', 'comments-form-user-name', 0);
					}
					break;
				case 1:
					if (!$user->id) {
						$tmpl->addVar('tpl_form', 'comments-form-user-name', 1);
						$tmpl->addVar('tpl_form', 'comments-form-user-name-required', 0);
					} else {
						$tmpl->addVar('tpl_form', 'comments-form-user-name', 0);
					}
					break;
				case 0:
				default:
					$tmpl->addVar('tpl_form', 'comments-form-user-name', 0);
					break;
			}


			switch ((int) $config->get('author_email')) {
				case 2:
					if (!$user->id) {
						$tmpl->addVar('tpl_form', 'comments-form-email-required', 1);
						$tmpl->addVar('tpl_form', 'comments-form-user-email', 1);
					} else {
						$tmpl->addVar('tpl_form', 'comments-form-user-email', 0);
					}
					break;
				case 1:
					if (!$user->id) {
						$tmpl->addVar('tpl_form', 'comments-form-user-email', 1);
					} else {
						$tmpl->addVar('tpl_form', 'comments-form-user-email', 0);
					}
					break;
				case 0:
				default:
					$tmpl->addVar('tpl_form', 'comments-form-user-email', 0);

					if (!$user->id) {
						$tmpl->addVar('tpl_form', 'comments-form-subscribe', 0);
					}
					break;
			}

			$tmpl->addVar('tpl_form', 'comments-form-homepage-required', 0);

			switch ((int) $config->get('author_homepage')) {
				case 5:
					if (!$user->id) {
						$tmpl->addVar('tpl_form', 'comments-form-homepage-required', 0);
						$tmpl->addVar('tpl_form', 'comments-form-user-homepage', 1);
					} else {
						$tmpl->addVar('tpl_form', 'comments-form-user-homepage', 0);
					}
					break;
				case 4:
					if (!$user->id) {
						$tmpl->addVar('tpl_form', 'comments-form-homepage-required', 1);
						$tmpl->addVar('tpl_form', 'comments-form-user-homepage', 1);
					} else {
						$tmpl->addVar('tpl_form', 'comments-form-user-homepage', 0);
					}
					break;
				case 3:
					$tmpl->addVar('tpl_form', 'comments-form-homepage-required', 1);
					$tmpl->addVar('tpl_form', 'comments-form-user-homepage', 1);
					break;
				case 2:
					if (!$user->id) {
						$tmpl->addVar('tpl_form', 'comments-form-homepage-required', 1);
					}
					$tmpl->addVar('tpl_form', 'comments-form-user-homepage', 1);
					break;
				case 1:
					$tmpl->addVar('tpl_form', 'comments-form-user-homepage', 1);
					break;
				case 0:
				default:
					$tmpl->addVar('tpl_form', 'comments-form-user-homepage', 0);
					break;
			}

			$tmpl->addVar('tpl_form', 'comments-form-title-required', 0);

			switch ((int) $config->get('comment_title')) {
				case 3:
					$tmpl->addVar('tpl_form', 'comments-form-title-required', 1);
					$tmpl->addVar('tpl_form', 'comments-form-title', 1);
					break;
				case 1:
					$tmpl->addVar('tpl_form', 'comments-form-title', 1);
					break;
				case 0:
				default:
					$tmpl->addVar('tpl_form', 'comments-form-title', 0);
					break;
			}

			$result = $tmpl->renderTemplate('tpl_form');

			// support old-style templates
			$result = str_replace('name="captcha-refid"', 'name="captcha_refid"', $result);

			if ($user->id) {
				$result = str_replace('</form>',
					'<div><input type="hidden" name="userid" value="' . $user->id . '" /></div></form>',
					$result);
			}

			return $result;
		} else {
			$message = $acl->getUserBlocked()
							? JCommentsText::getMessagesBasedOnLanguage($config->get('messages_fields'), 'message_banned', $lang->getTag())
							: JCommentsText::getMessagesBasedOnLanguage($config->get('messages_fields'), 'message_policy_whocancomment', $lang->getTag());
			if ($message != '') {
				$header = JText::_('FORM_HEADER');
				$message = stripslashes($message);
				if ($message == strip_tags($message)) {
					$message = nl2br($message);
				}
			} else {
				$header = '';
				$message = '';
			}

			$tmpl->addVar('tpl_form', 'comments-form-message', 1);
			$tmpl->addVar('tpl_form', 'comments-form-message-header', $header);
			$tmpl->addVar('tpl_form', 'comments-form-message-text', $message);

			return $tmpl->renderTemplate('tpl_form');
		}
	}

	public static function getCommentsReportForm($id, $object_id, $object_group)
	{
		$id = (int)$id;
		$object_group = JCommentsSecurity::clearObjectGroup($object_group);

		$user = JFactory::getUser();
		$tmpl = JCommentsFactory::getTemplate($object_id, $object_group);
		$tmpl->load('tpl_report_form');
		$tmpl->addVar('tpl_report_form', 'comment-id', $id);
		$tmpl->addVar('tpl_report_form', 'isGuest', (int) $user->get('guest'));
		$result = $tmpl->renderTemplate('tpl_report_form');

		return $result;
	}

	public static function getCommentsList($object_id, $object_group = 'com_content', $page = 0)
	{
		$object_id = (int)$object_id;
		$object_group = JCommentsSecurity::clearObjectGroup($object_group);

		$user = JFactory::getUser();
		$acl = JCommentsFactory::getACL();
		$config = ComponentHelper::getParams('com_jcomments');

		$comments_per_page = (int) $config->get('comments_per_page');

		$limitstart = 0;
		$total = JComments::getCommentsCount($object_id, $object_group);

		if ($user->authorise('comment.comment', 'com_jcomments') == 0 && $total == 0) {
			return '';
		}

		if ($total > 0) {
			$options = array();
			$options['object_id'] = $object_id;
			$options['object_group'] = $object_group;
			$options['published'] = $acl->canPublish() || $acl->canPublishForObject($object_id, $object_group) ? null : 1;
			$options['votes'] = (int) $config->get('enable_voting');

			if ($comments_per_page > 0) {
				$page = (int)$page;

				require_once(JPATH_ROOT . '/components/com_jcomments/helpers/pagination.php');
				$pagination = new JCommentsPagination($object_id, $object_group);
				$pagination->setCurrentPage($page);

				$total_pages = $pagination->getTotalPages();
				$this_page = $pagination->getCurrentPage();
				$limitstart = $pagination->getLimitStart();
				$comments_per_page = $pagination->getCommentsPerPage();

				$options['limit'] = $comments_per_page;
				$options['limitStart'] = $limitstart;
			}

			$rows = JCommentsModel::getCommentsList($options);
		} else {
			$rows = array();
		}

		$tmpl = JCommentsFactory::getTemplate($object_id, $object_group);
		$tmpl->load('tpl_list');
		$tmpl->load('tpl_comment');

		if (count($rows)) {

			$isLocked = ((int) $config->get('comments_locked', 0) == 1);

			$tmpl->addVar('tpl_list', 'comments-refresh', intval(!$isLocked));
			$tmpl->addVar('tpl_list', 'comments-rss', intval((int) $config->get('enable_rss') && !$isLocked));
			$tmpl->addVar('tpl_list', 'comments-can-subscribe', intval($user->id && $user->authorise('comment.subscribe', 'com_jcomments') && !$isLocked));
			$tmpl->addVar('tpl_list', 'comments-count', count($rows));

			if ($user->id && $user->authorise('comment.subscribe', 'com_jcomments')) {
				require_once(JPATH_ROOT . '/components/com_jcomments/jcomments.subscription.php');
				$manager = JCommentsSubscriptionManager::getInstance();
				$isSubscribed = $manager->isSubscribed($object_id, $object_group, $user->id);
				$tmpl->addVar('tpl_list', 'comments-user-subscribed', $isSubscribed);
			}

			if ($config->get('comments_list_order') == 'DESC') {
				if ($comments_per_page > 0) {
					$i = $total - ($comments_per_page * ($page > 0 ? $page - 1 : 0));
				} else {
					$i = count($rows);
				}
			} else {
				$i = $limitstart + 1;
			}

			JCommentsEvent::trigger('onJCommentsCommentsPrepare', array(&$rows));

			if ($user->authorise('comment.gravatar', 'com_jcomments')) {
				JCommentsEvent::trigger('onPrepareAvatars', array(&$rows));
			}

			$items = array();

			foreach ($rows as $row) {
				// run autocensor, replace quotes, smilies and other pre-view processing
				JComments::prepareComment($row);

				// setup toolbar
				if (!$acl->canModerate($row)) {
					$tmpl->addVar('tpl_comment', 'comments-panel-visible', 0);
				} else {
					$tmpl->addVar('tpl_comment', 'comments-panel-visible', 1);
					$tmpl->addVar('tpl_comment', 'button-edit', $acl->canEdit($row));
					$tmpl->addVar('tpl_comment', 'button-delete', $acl->canDelete($row));
					$tmpl->addVar('tpl_comment', 'button-publish', $acl->canPublish($row));
					$tmpl->addVar('tpl_comment', 'button-ip', $acl->canViewIP($row));
					$tmpl->addVar('tpl_comment', 'button-ban', $acl->canBan($row));
				}

				$tmpl->addVar('tpl_comment', 'comment-show-vote', (int) $config->get('enable_voting'));
				$tmpl->addVar('tpl_comment', 'comment-show-email', $acl->canViewEmail($row));
				$tmpl->addVar('tpl_comment', 'comment-show-homepage', $acl->canViewHomepage($row));
				$tmpl->addVar('tpl_comment', 'comment-show-title', (int) $config->get('comment_title'));
				$tmpl->addVar('tpl_comment', 'button-vote', $acl->canVote($row));
				$tmpl->addVar('tpl_comment', 'button-quote', $acl->canQuote($row));
				$tmpl->addVar('tpl_comment', 'button-reply', $acl->canReply($row));
				$tmpl->addVar('tpl_comment', 'button-report', $acl->canReport($row));
				$tmpl->addVar('tpl_comment', 'avatar', $user->authorise('comment.gravatar', 'com_jcomments') && !$row->deleted);

				$tmpl->addObject('tpl_comment', 'comment', $row);

				if (isset($row->_number)) {
					$tmpl->addVar('tpl_comment', 'comment-number', $row->_number);
				} else {
					$tmpl->addVar('tpl_comment', 'comment-number', $i);

					if ($config->get('comments_list_order') == 'DESC') {
						$i--;
					} else {
						$i++;
					}
				}

				$items[$row->id] = $tmpl->renderTemplate('tpl_comment');
			}

			$tmpl->addObject('tpl_list', 'comments-items', $items);

			// build page navigation
			if (($comments_per_page > 0) && ($total_pages > 1)) {
				$tmpl->addVar('tpl_list', 'comments-nav-first', 1);
				$tmpl->addVar('tpl_list', 'comments-nav-total', $total_pages);
				$tmpl->addVar('tpl_list', 'comments-nav-active', $this_page);

				$pagination = $config->get('comments_pagination');

				// show top pagination
				if (($pagination == 'both') || ($pagination == 'top')) {
					$tmpl->addVar('tpl_list', 'comments-nav-top', 1);
				}

				// show bottom pagination
				if (($pagination == 'both') || ($pagination == 'bottom')) {
					$tmpl->addVar('tpl_list', 'comments-nav-bottom', 1);
				}
			}
			unset($rows);
		}

		return $tmpl->renderTemplate('tpl_list');
	}

	public static function getCommentsTree($object_id, $object_group = 'com_content', $page = 0)
	{
		$object_id = (int)$object_id;
		$object_group = JCommentsSecurity::clearObjectGroup($object_group);

		$user = JFactory::getUser();
		$acl = JCommentsFactory::getACL();
		$config = ComponentHelper::getParams('com_jcomments');

		$total = JComments::getCommentsCount($object_id, $object_group);

		if ($user->authorise('comment.comment', 'com_jcomments') == 0 && $total == 0) {
			return '';
		}

		if ($total > 0) {
			$options = array();
			$options['object_id'] = $object_id;
			$options['object_group'] = $object_group;
			$options['published'] = $acl->canPublish() || $acl->canPublishForObject($object_id, $object_group) ? null : 1;
			$options['votes'] = (int) $config->get('enable_voting');

			$rows = JCommentsModel::getCommentsList($options);
		} else {
			$rows = array();
		}

		$tmpl = JCommentsFactory::getTemplate($object_id, $object_group);
		$tmpl->load('tpl_tree');
		$tmpl->load('tpl_comment');

		if (count($rows)) {

			$isLocked = ((int) $config->get('comments_locked', 0) == 1);

			$tmpl->addVar('tpl_tree', 'comments-refresh', intval(!$isLocked));
			$tmpl->addVar('tpl_tree', 'comments-rss', intval((int) $config->get('enable_rss') && !$isLocked));
			$tmpl->addVar('tpl_tree', 'comments-can-subscribe', intval($user->id && $user->authorise('comment.subscribe', 'com_jcomments') && !$isLocked));
			$tmpl->addVar('tpl_tree', 'comments-count', count($rows));

			if ($user->id && $user->authorise('comment.subscribe', 'com_jcomments')) {
				require_once(JPATH_ROOT . '/components/com_jcomments/jcomments.subscription.php');
				$manager = JCommentsSubscriptionManager::getInstance();
				$isSubscribed = $manager->isSubscribed($object_id, $object_group, $user->id);
				$tmpl->addVar('tpl_tree', 'comments-user-subscribed', $isSubscribed);
			}

			$i = 1;

			JCommentsEvent::trigger('onJCommentsCommentsPrepare', array(&$rows));

			if ($user->authorise('comment.gravatar', 'com_jcomments')) {
				JCommentsEvent::trigger('onPrepareAvatars', array(&$rows));
			}

			require_once(JPATH_ROOT . '/components/com_jcomments/libraries/joomlatune/tree.php');

			$tree = new JoomlaTuneTree($rows);
			$items = $tree->get();

			foreach ($rows as $row) {
				// run autocensor, replace quotes, smilies and other pre-view processing
				JComments::prepareComment($row);

				// setup toolbar
				if (!$acl->canModerate($row)) {
					$tmpl->addVar('tpl_comment', 'comments-panel-visible', 0);
				} else {
					$tmpl->addVar('tpl_comment', 'comments-panel-visible', 1);
					$tmpl->addVar('tpl_comment', 'button-edit', $acl->canEdit($row));
					$tmpl->addVar('tpl_comment', 'button-delete', $acl->canDelete($row));
					$tmpl->addVar('tpl_comment', 'button-publish', $acl->canPublish($row));
					$tmpl->addVar('tpl_comment', 'button-ip', $acl->canViewIP($row));
					$tmpl->addVar('tpl_comment', 'button-ban', $acl->canBan($row));
				}

				$tmpl->addVar('tpl_comment', 'comment-show-vote', (int) $config->get('enable_voting'));
				$tmpl->addVar('tpl_comment', 'comment-show-email', $acl->canViewEmail($row));
				$tmpl->addVar('tpl_comment', 'comment-show-homepage', $acl->canViewHomepage($row));
				$tmpl->addVar('tpl_comment', 'comment-show-title', (int) $config->get('comment_title'));
				$tmpl->addVar('tpl_comment', 'button-vote', $acl->canVote($row));
				$tmpl->addVar('tpl_comment', 'button-quote', $acl->canQuote($row));
				$tmpl->addVar('tpl_comment', 'button-reply', $acl->canReply($row));
				$tmpl->addVar('tpl_comment', 'button-report', $acl->canReport($row));
				$tmpl->addVar('tpl_comment', 'avatar', $user->authorise('comment.gravatar', 'com_jcomments') && !$row->deleted);

				if (isset($items[$row->id])) {
					$tmpl->addVar('tpl_comment', 'comment-number', '');
					$tmpl->addObject('tpl_comment', 'comment', $row);
					$items[$row->id]->html = $tmpl->renderTemplate('tpl_comment');
					$i++;
				}
			}

			$tmpl->addObject('tpl_tree', 'comments-items', $items);

			unset($rows);
		}

		return $tmpl->renderTemplate('tpl_tree');
	}

	public static function getCommentItem(&$comment)
	{
		$user = Factory::getApplication()->getIdentity();
		$acl = JCommentsFactory::getACL();
		$config = ComponentHelper::getParams('com_jcomments');

		if ($user->authorise('comment.gravatar', 'com_jcomments')) {
			JCommentsEvent::trigger('onPrepareAvatar', array(&$comment));
		}

		// run autocensor, replace quotes, smilies and other pre-view processing
		JComments::prepareComment($comment);

		$tmpl = JCommentsFactory::getTemplate($comment->object_id, $comment->object_group);
		$tmpl->load('tpl_comment');

		// setup toolbar
		if (!$acl->canModerate($comment)) {
			$tmpl->addVar('tpl_comment', 'comments-panel-visible', 'visibility', 0);
		} else {
			$tmpl->addVar('tpl_comment', 'comments-panel-visible', 1);
			$tmpl->addVar('tpl_comment', 'button-edit', $acl->canEdit($comment));
			$tmpl->addVar('tpl_comment', 'button-delete', $acl->canDelete($comment));
			$tmpl->addVar('tpl_comment', 'button-publish', $acl->canPublish($comment));
			$tmpl->addVar('tpl_comment', 'button-ip', $acl->canViewIP($comment));
			$tmpl->addVar('tpl_comment', 'button-ban', $acl->canBan($comment));
			$tmpl->addVar('tpl_comment', 'comment-show-email', $acl->canViewEmail());
			$tmpl->addVar('tpl_comment', 'comment-show-homepage', $acl->canViewHomepage());
		}

		$tmpl->addVar('tpl_comment', 'comment-show-vote', (int) $config->get('enable_voting'));
		$tmpl->addVar('tpl_comment', 'comment-show-email', $acl->canViewEmail($comment));
		$tmpl->addVar('tpl_comment', 'comment-show-homepage', $acl->canViewHomepage($comment));
		$tmpl->addVar('tpl_comment', 'comment-show-title', (int) $config->get('comment_title'));
		$tmpl->addVar('tpl_comment', 'button-vote', $acl->canVote($comment));
		$tmpl->addVar('tpl_comment', 'button-quote', $acl->canQuote($comment));
		$tmpl->addVar('tpl_comment', 'button-reply', $acl->canReply($comment));
		$tmpl->addVar('tpl_comment', 'button-report', $acl->canReport($comment));
		$tmpl->addVar('tpl_comment', 'comment-number', '');
		$tmpl->addVar('tpl_comment', 'avatar', $user->authorise('comment.gravatar', 'com_jcomments') && !$comment->deleted);

		$tmpl->addObject('tpl_comment', 'comment', $comment);

		return $tmpl->renderTemplate('tpl_comment');
	}

	public static function getCommentListItem(&$comment)
	{
		$total = JComments::getCommentsCount($comment->object_id, $comment->object_group,
			'parent = ' . $comment->parent);

		$tmpl = JCommentsFactory::getTemplate($comment->object_id, $comment->object_group);
		$tmpl->load('tpl_list');
		$tmpl->addVar('tpl_list', 'comment-id', $comment->id);
		$tmpl->addVar('tpl_list', 'comment-item', JComments::getCommentItem($comment));
		$tmpl->addVar('tpl_list', 'comment-modulo', $total % 2 ? 1 : 0);

		return $tmpl->renderTemplate('tpl_list');
	}

	/**
	 * Sends notification about new/updated comment to administrators
	 *
	 * @param JCommentsTableComment $comment The comment object
	 * @param boolean $isNew True if the comment is new
	 * @return void
	 */
	public static function sendNotification(&$comment, $isNew = true)
	{
		$data = array();
		$data['comment'] = clone $comment;

		JCommentsNotification::push($data, $isNew ? 'moderate-new' : 'moderate-update');
	}

	/**
	 * Sends user's report to administrators
	 *
	 * @param JCommentsTableComment $comment The comment object
	 * @param string $name The reporter's name
	 * @param string $reason The report description
	 * @return void
	 */
	public static function sendReport(&$comment, $name, $reason = '')
	{
		$data = array();
		$data['comment'] = clone $comment;
		$data['report-name'] = $name;
		$data['report-reason'] = $reason;

		JCommentsNotification::push($data, 'report');
	}

	/**
	 * Sends notification about new or updated comment to subscribers
	 *
	 * @param JCommentsTableComment $comment The comment object
	 * @param boolean $isNew True if the comment is new
	 * @return void
	 */
	public static function sendToSubscribers(&$comment, $isNew = true)
	{
		if ($comment->published) {
			$data = array();
			$data['comment'] = clone $comment;

			JCommentsNotification::push($data, $isNew ? 'comment-new' : 'comment-update');
		}
	}

	public static function prepareComment(&$comment)
	{
		if (isset($comment->_skip_prepare) && $comment->_skip_prepare == 1) {
			return;
		}

		JCommentsEvent::trigger('onJCommentsCommentBeforePrepare', array(&$comment));

		$config = ComponentHelper::getParams('com_jcomments');
		$acl = JCommentsFactory::getACL();
		$user = Factory::getApplication()->getIdentity();

		// Run autocensor
		if ($acl->enableAutocensor()) {
			$comment->comment = JCommentsText::censor($comment->comment);

			if ($comment->title != '') {
				$comment->title = JCommentsText::censor($comment->title);
			}
		}

		// replace deleted comment text with predefined message
		if ($comment->deleted == 1) {
			$comment->comment = JText::_('COMMENT_TEXT_COMMENT_HAS_BEEN_DELETED');
			$comment->username = '';
			$comment->name = '';
			$comment->email = '';
			$comment->homepage = '';
			$comment->userid = 0;
			$comment->isgood = 0;
			$comment->ispoor = 0;
		}

		// replace BBCode tags
		$comment->comment = JCommentsFactory::getBBCode()->replace($comment->comment);

		if ((int) $config->get('enable_custom_bbcode')) {
			$comment->comment = JCommentsFactory::getCustomBBCode()->replace($comment->comment);
		}

		// fix long words problem
		$word_maxlength = (int) $config->get('word_maxlength');
		if ($word_maxlength > 0) {
			$comment->comment = JCommentsText::fixLongWords($comment->comment, $word_maxlength);
			if ($comment->title != '') {
				$comment->title = JCommentsText::fixLongWords($comment->title, $word_maxlength);
			}
		}

		if ($user->authorise('comment.email.protect', 'com_jcomments')) {
			$comment->comment = JComments::maskEmail($comment->id, $comment->comment);
		}

		// autolink urls
		if ($user->authorise('comment.autolink', 'com_jcomments')) {
			$comment->comment = preg_replace_callback(_JC_REGEXP_LINK, array('JComments', 'urlProcessor'), $comment->comment);

			if ($user->authorise('comment.email.protect', 'com_jcomments') != 1) {
				$comment->comment = preg_replace(_JC_REGEXP_EMAIL, '<a href="mailto:\\1@\\2">\\1@\\2</a>', $comment->comment);
			}
		}

		// replace smilies' codes with images
		if ($config->get('enable_smilies') == '1') {
			$comment->comment = JCommentsFactory::getSmilies()->replace($comment->comment);
		}

		$comment->author = JCommentsContent::getCommentAuthorName($comment);

		// Gravatar support
		$comment->gravatar = md5(strtolower($comment->email));

		if (empty($comment->avatar)) {
			$comment->avatar = '<img src="https://www.gravatar.com/avatar/' . $comment->gravatar . '?d=' . urlencode(JCommentsFactory::getLink('noavatar')) . '" alt="' . htmlspecialchars($comment->author) . '" />';
		}

		JCommentsEvent::trigger('onJCommentsCommentAfterPrepare', array(&$comment));
	}

	public static function maskEmail($id, $text)
	{
		$id = (int)$id;

		if ($id) {
			$image = str_replace('/administrator', '', JURI::root()) . 'media/com_jcomments/images/email.png';

			$matches = array();
			$count = preg_match_all(_JC_REGEXP_EMAIL, $text, $matches);
			for ($i = 0; $i < $count; $i++) {
				$html = '<span onclick="jcomments.jump2email(' . $id . ', \'' . md5($matches[0][$i]) . '\');" class="email">';
				$html .= $matches[1][$i] . '<img src="' . $image . '" alt="@" />' . $matches[2][$i];
				$html .= '</span>';
				$text = str_replace($matches[0][$i], $html, $text);
			}
		}

		return $text;
	}

	public static function urlProcessor(&$matches)
	{
		$link = $matches[2];
		$link_suffix = '';

		while (preg_match('#[\,\.]+#', $link[strlen($link) - 1])) {
			$sl = strlen($link) - 1;
			$link_suffix .= $link[$sl];
			$link = StringHelper::substr($link, 0, $sl);
		}

		$link_text = preg_replace('#(http|https|news|ftp)\:\/\/#i', '', $link);

		$config = ComponentHelper::getParams('com_jcomments');
		$link_maxlength = (int) $config->get('link_maxlength');

		if (($link_maxlength > 0) && (strlen($link_text) > $link_maxlength)) {
			$linkParts = preg_split('#\/#i', preg_replace('#/$#i', '', $link_text));
			$cnt = count($linkParts);

			if ($cnt >= 2) {
				$linkSite = $linkParts[0];
				$linkDocument = $linkParts[$cnt - 1];
				$shortLink = $linkSite . '/.../' . $linkDocument;

				if ($cnt == 2) {
					$shortLink = $linkSite . '/.../';
				} else if (strlen($shortLink) > $link_maxlength) {
					$linkSite = str_replace('www.', '', $linkSite);
					$linkSiteLength = strlen($linkSite);
					$shortLink = $linkSite . '/.../' . $linkDocument;

					if (strlen($shortLink) > $link_maxlength) {
						if ($linkSiteLength < $link_maxlength) {
							$shortLink = $linkSite . '/.../...';
						} else if ($linkDocument < $link_maxlength) {
							$shortLink = '.../' . $linkDocument;
						} else {
							$link_protocol = preg_replace('#([^a-z])#i', '', $matches[3]);

							if ($link_protocol == 'www') {
								$link_protocol = 'http';
							}

							if ($link_protocol != '') {
								$shortLink = $link_protocol;
							} else {
								$shortLink = '/.../';
							}
						}
					}
				}
				$link_text = wordwrap($shortLink, $link_maxlength, ' ', true);
			} else {
				$link_text = wordwrap($link_text, $link_maxlength, ' ', true);
			}
		}

		$liveSite = trim(str_replace(JURI::root(true), '', str_replace('/administrator', '', JURI::root())), '/');
		if (strpos($link, $liveSite) === false) {
			return $matches[1] . "<a href=\"" . ((StringHelper::substr($link, 0, 3) == 'www') ? "http://" : "") . $link . "\" target=\"_blank\" rel=\"external nofollow\">$link_text</a>" . $link_suffix;
		} else {
			return $matches[1] . "<a href=\"$link\" target=\"_blank\">$link_text</a>" . $link_suffix;
		}
	}

	public static function getCommentPage($object_id, $object_group, $comment_id)
	{
		$config = ComponentHelper::getParams('com_jcomments');
		$object_group = JCommentsSecurity::clearObjectGroup($object_group);
		
		if ((int) $config->get('comments_per_page') > 0) {
			require_once(JPATH_ROOT . '/components/com_jcomments/helpers/pagination.php');
			$pagination = new JCommentsPagination($object_id, $object_group);
			$this_page = $pagination->getCommentPage($object_id, $object_group, $comment_id);
		} else {
			$this_page = 0;
		}

		return $this_page;
	}

	public static function unsubscribe()
	{
		$app = JFactory::getApplication('site');
		$hash = $app->input->get('hash', '');
		$hash = preg_replace('#[^A-Z0-9]#i', '', $hash);

		if ($hash) {
			require_once(JPATH_ROOT . '/components/com_jcomments/jcomments.subscription.php');
			$manager = JCommentsSubscriptionManager::getInstance();
			$subscription = $manager->getSubscriptionByHash($hash);
			$result = $manager->unsubscribeByHash($hash);
			if ($result) {
				$link = JCommentsObject::getLink($subscription->object_id, $subscription->object_group, $subscription->lang);
				if (empty($link)) {
					$link = JRoute::_('index.php');
				}

				$app->redirect($link, JText::_('SUCCESSFULLY_UNSUBSCRIBED'));
			}
		}

		header('HTTP/1.0 404 Not Found');
		JError::raiseError(404, 'JGLOBAL_RESOURCE_NOT_FOUND');
		exit(404);
	}

	public static function executeCmd()
	{
		$app = JFactory::getApplication('site');
		$cmd = strtolower($app->input->get('cmd', ''));
		$hash = $app->input->get('hash', '');
		$id = $app->input->getInt('id', 0);

		$message = '';
		$link = str_replace('/administrator', '', JURI::root()) . 'index.php';

		$checkHash = JCommentsFactory::getCmdHash($cmd, $id);

		if ($hash == $checkHash) {
			$config = ComponentHelper::getParams('com_jcomments');
			if ((int) $config->get('enable_quick_moderation') == 1) {
				JTable::addIncludePath(JPATH_ROOT . '/administrator/components/com_jcomments/tables');

				$comment = JTable::getInstance('Comment', 'JCommentsTable');
				if ($comment->load($id)) {
					$link = JCommentsObject::getLink($comment->object_id, $comment->object_group, $comment->lang);
					$link = str_replace('&amp;', '&', $link);
					switch ($cmd) {
						case 'publish':
							$comment->published = 1;
							$comment->store();

							// send notification to comment subscribers
							JComments::sendToSubscribers($comment, true);

							$link .= '#comment-' . $comment->id;
							break;

						case 'unpublish':
							$comment->published = 0;
							$comment->store();

							$acl = JCommentsFactory::getACL();
							if ($acl->canPublish()) {
								$link .= '#comment-' . $comment->id;
							} else {
								$link .= '#comments';
							}
							break;

						case 'delete':
							if ((int) $config->get('delete_mode') == 0) {
								$comment->delete();
								$link .= '#comments';
							} else {
								$comment->markAsDeleted();
								$link .= '#comment-' . $comment->id;
							}
							break;

						case 'ban':
							if ((int) $config->get('enable_blacklist') == 1) {
								$acl = JCommentsFactory::getACL();
								// we will not ban own IP ;)
								if ($comment->ip != $_SERVER['REMOTE_ADDR']) {
									$options = array();
									$options['ip'] = $comment->ip;

									// check if this IP already banned
									if (JCommentsSecurity::checkBlacklist($options)) {
										$blacklist = JTable::getInstance('Blacklist', 'JCommentsTable');
										$blacklist->ip = $comment->ip;
										$blacklist->store();
										$message = JText::_('SUCCESSFULLY_BANNED');
									} else {
										$message = JText::_('ERROR_IP_ALREADY_BANNED');
									}
								} else {
									$message = JText::_('ERROR_YOU_CAN_NOT_BAN_YOUR_IP');
								}
							}
							break;
					}

					JCommentsNotification::send();
				} else {
					$message = JText::_('ERROR_NOT_FOUND');
				}
			} else {
				$message = JText::_('ERROR_QUICK_MODERATION_DISABLED');
			}
		} else {
			$message = JText::_('ERROR_QUICK_MODERATION_INCORRECT_HASH');
		}

		$app->redirect($link, $message);
	}

	public static function getCommentsCount($object_id, $object_group = 'com_content', $filter = '')
	{
		$acl = JCommentsFactory::getACL();
		$object_group = JCommentsSecurity::clearObjectGroup($object_group);

		$options = array();
		$options['object_id'] = (int)$object_id;
		$options['object_group'] = trim($object_group);
		$options['published'] = $acl->canPublish() || $acl->canPublishForObject($object_id, $object_group) ? null : 1;
		$options['filter'] = $filter;

		return JCommentsModel::getCommentsCount($options);
	}
}
