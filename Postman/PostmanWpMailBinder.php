<?php
require_once 'PostmanWpMail.php';
require_once 'PostmanMessageHandler.php';
require_once 'PostmanOptions.php';
if (! class_exists ( "PostmanWpMailBinder" )) {
	class PostmanWpMailBinder {
		private $logger;
		private $basename;
		private $couldNotReplaceWpMail;
		private $messageHandler;
		function __construct($basename, PostmanOptions $binderOptions, PostmanAuthorizationToken $binderAuthorizationToken, PostmanMessageHandler $messageHandler) {
			assert ( ! empty ( $basename ) );
			assert ( ! empty ( $binderOptions ) );
			assert ( ! empty ( $binderAuthorizationToken ) );
			assert ( ! empty ( $messageHandler ) );
			$this->basename = $basename;
			$this->messageHandler = $messageHandler;
			$this->logger = new PostmanLogger ( get_class ( $this ) );
			add_action ( 'admin_init', array (
					$this,
					'warnIfCanNotBindToWpMail' 
			) );
			if ($binderOptions->isSendingEmailAllowed ( $binderAuthorizationToken )) {
				
				if (! function_exists ( 'wp_mail' )) {
					$this->logger->debug ( 'Binding to wp_mail()' );
					/**
					 * The Postman drop-in replacement for the WordPress wp_mail() function
					 *
					 * @param string|array $to
					 *        	Array or comma-separated list of email addresses to send message.
					 * @param string $subject
					 *        	Email subject
					 * @param string $message
					 *        	Message contents
					 * @param string|array $headers
					 *        	Optional. Additional headers.
					 * @param string|array $attachments
					 *        	Optional. Files to attach.
					 * @return bool Whether the email contents were sent successfully.
					 */
					function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
						// get the Options and AuthToken
						$wp_mail_options = PostmanOptions::getInstance ();
						$wp_mail_authToken = PostmanAuthorizationToken::getInstance ();
						// create an instance of PostmanWpMail to send the message
						$wp_mail_postmanWpMail = new PostmanWpMail ();
						// send the message
						return $wp_mail_postmanWpMail->send ( $wp_mail_options, $wp_mail_authToken, $to, $subject, $message, $headers, $attachments );
					}
				} else {
					$this->logger->debug ( 'cant replace wp_mail' );
					$this->couldNotReplaceWpMail = true;
				}
			} else {
				$this->logger->debug ( 'Not attemping to bind, plugin is not configured.' );
			}
		}
		function warnIfCanNotBindToWpMail() {
			if (is_plugin_active ( $this->basename )) {
				if ($this->couldNotReplaceWpMail) {
					$this->logger->debug ( 'oops, can not bind to wp_mail()' );
					add_action ( 'admin_notices', Array (
							$this,
							'displayCouldNotReplaceWpMail' 
					) );
				}
			}
		}
		public function displayCouldNotReplaceWpMail() {
			$this->messageHandler->displayWarningMessage ( PostmanAdminController::NAME . ' is properly configured, but another plugin has taken over the mail service. Deactivate the other plugin.' );
		}
	}
}