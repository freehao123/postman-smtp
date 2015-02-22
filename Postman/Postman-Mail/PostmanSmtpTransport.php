<?php
if (! class_exists ( 'PostmanSmtpTransport' )) {
	class PostmanSmtpTransport implements PostmanTransport {
		private $logger;
		public function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		const SLUG = 'smtp';
		public function isSmtp() {
			return true;
		}
		public function isServiceProviderGoogle($hostname) {
			return endsWith ( $hostname, 'gmail.com' );
		}
		public function isServiceProviderMicrosoft($hostname) {
			return endsWith ( $hostname, 'live.com' );
		}
		public function isServiceProviderYahoo($hostname) {
			return endsWith ( $hostname, 'yahoo.com' );
		}
		public function isOAuthUsed($authType) {
			return $authType == PostmanOptions::AUTHENTICATION_TYPE_OAUTH2;
		}
		public function isTranscriptSupported() {
			return true;
		}
		public function getSlug() {
			return self::SLUG;
		}
		public function getName() {
			return _x ( 'SMTP', 'Transport Name', 'postman-smtp' );
		}
		public function createZendMailTransport($hostname, $config) {
			return new Zend_Mail_Transport_Smtp ( $hostname, $config );
		}
		public function getDeliveryDetails(PostmanOptionsInterface $options) {
			$deliveryDetails ['transport_name'] = getTransportDescription ( $options->getEncryptionType () );
			$deliveryDetails ['host'] = $options->getHostname () . ':' . $options->getPort ();
			$deliveryDetails ['auth_desc'] = $this->getAuthenticationDescription ( $options->getAuthorizationType () );
			/* translators: where %1$s is the transport type, %2$s is the host, and %3$s is the Authentication Type (e.g. Postman will send mail via smtp.gmail.com:465 using OAuth 2.0 authentication.) */
			return sprintf ( __ ( 'Postman will send mail via %1$s to %2$s using %3$s authentication.', 'postman-smtp' ), '<b>' . $deliveryDetails ['transport_name'] . '</b>', '<b>' . $deliveryDetails ['host'] . '</b>', '<b>' . $deliveryDetails ['auth_desc'] . '</b>' );
		}
		private function getTransportDescription($encType) {
			$deliveryDetails = $this->getName ();
			if ($encType != PostmanOptions::ENCRYPTION_TYPE_NONE) {
				/* translators: where %1$s is the Transport type (e.g. SMTP or SMTPS) and %2$s is the encryption type (e.g. SSL or TLS) */
				$deliveryDetails = sprintf ( '%1$s-%2$s', _x ( 'SMTPS', 'Transport Name', 'postman-smtp' ), strtoupper ( $encType ) );
			}
			return $deliveryDetails;
		}
		private function getAuthenticationDescription($authType) {
			if (PostmanOptions::AUTHENTICATION_TYPE_OAUTH2 == $authType) {
				return _x ( 'OAuth 2.0', 'Authentication Type', 'postman-smtp' );
			} else if (PostmanOptions::AUTHENTICATION_TYPE_NONE == $authType) {
				return _x ( 'no', 'Authentication Type', 'postman-smtp' );
			} else {
				/* translators: where %s is the Authentication Type (e.g. plain, login or crammd5) */
				return sprintf ( _x ( 'Password (%s)', 'Authentication Type', 'postman-smtp' ), $authType );
			}
		}
		public function isConfigured(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			// This is configured if:
			$configured = true;
			
			// 1. the transport is configured
			$configured &= $this->isTransportConfigured ( $options );
			
			// 2. if authentication is enabled, that it is configured
			if ($options->isAuthTypePassword ()) {
				$configured &= $this->isPasswordAuthenticationConfigured ( $options );
			} else if ($options->isAuthTypeOAuth2 ()) {
				$configured &= $this->isOAuthAuthenticationConfigured ( $options );
			}
			
			// return the status
			return $configured;
		}
		/**
		 * The transport can have all the configuration it needs, but still not be ready for use
		 * Check to see if permission is required from the OAuth 2.0 provider
		 *
		 * @param PostmanOptionsInterface $options        	
		 * @param PostmanOAuthToken $token        	
		 * @return boolean
		 */
		public function isReady(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			// 1. is the transport configured
			$configured = $this->isConfigured ( $options, $token );
			
			// 2. do we have permission from the OAuth 2.0 provider
			$configured &= ! $this->isPermissionNeeded ( $token );
			
			return $configured;
		}
		private function isTransportConfigured(PostmanOptionsInterface $options) {
			$hostname = $options->getHostname ();
			$port = $options->getPort ();
			return ! (empty ( $hostname ) || empty ( $port ));
		}
		private function isPasswordAuthenticationConfigured(PostmanOptionsInterface $options) {
			$username = $options->getUsername ();
			$password = $options->getPassword ();
			return $options->isAuthTypePassword () && ! (empty ( $username ) || empty ( $password ));
		}
		private function isOAuthAuthenticationConfigured(PostmanOptionsInterface $options) {
			$clientId = $options->getClientId ();
			$clientSecret = $options->getClientSecret ();
			$senderEmail = $options->getSenderEmail ();
			$hostname = $options->getHostname ();
			return $options->isAuthTypeOAuth2 () && ! (empty ( $clientId ) || empty ( $clientSecret ) || empty ( $senderEmail ) || ! $isOauthHost);
		}
		private function isPermissionNeeded(PostmanOAuthToken $token) {
			$accessToken = $token->getAccessToken ();
			$refreshToken = $token->getRefreshToken ();
			return ! (empty ( $accessToken ) || empty ( $refreshToken ));
		}
		public function getMisconfigurationMessage(PostmanConfigTextHelper $scribe, PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			if (! $this->isTransportConfigured ( $options )) {
				return __ ( 'Outgoing Mail Server (SMTP) and Port can not be empty.', 'postman-smtp' );
			} else if (! $this->isPasswordAuthenticationConfigured ( $options )) {
				return __ ( 'Password authentication (Plain/Login/CRAMMD5) requires a username and password.', 'postman-smtp' );
			} else if (! $this->isOAuthAuthenticationConfigured ( $options )) {
				/* translators: %1$s is the Client ID label, and %2$s is the Client Secret label (e.g. Warning: OAuth 2.0 authentication requires an OAuth 2.0-capable Outgoing Mail Server, Sender Email Address, Client ID, and Client Secret.) */
				$this->displayWarningMessage ( sprintf ( __ ( 'Warning: OAuth 2.0 authentication requires an OAuth 2.0-capable Outgoing Mail Server, Sender Email Address, %1$s, and %2$s.', 'postman-smtp' ), $scribe->getClientIdLabel (), $scribe->getClientSecretLabel () ) );
			} else if ($this->isPermissionNeeded ( $token )) {
				/* translators: %1$s is the Client ID label, and %2$s is the Client Secret label */
				$message = sprintf ( __ ( 'You have configured OAuth 2.0 authentication, but have not received permission to use it.', 'postman-smtp' ), $scribe->getClientIdLabel (), $scribe->getClientSecretLabel () );
				$message .= sprintf ( ' <a href="%s">%s</a>.', PostmanAdminController::getActionUrl ( PostmanAdminController::REQUEST_OAUTH2_GRANT_SLUG ), $scribe->getRequestPermissionLinkText () );
				return $message;
			}
		}
		
		/**
		 * Given a hostname, what ports should we test?
		 *
		 * May return an array of several combinations.
		 */
		public function getHostsToTest($hostname) {
			$hosts = array (
					array (
							'host' => $hostname,
							'port' => '25' 
					),
					array (
							'host' => $hostname,
							'port' => '465' 
					),
					array (
							'host' => $hostname,
							'port' => '587' 
					) 
			);
			return $hosts;
		}
		
		/**
		 * SMTP supports sending with these combinations in this order of preferences:
		 *
		 * 100 oauth on port 465 to smtp.gmail.com|smtp.live.com|yahoo.com
		 * 80 password/tls on port 587 to smtp.gmail.com|smtp.live.com|yahoo.com
		 * 60 password/ssl on port 465 to everybody
		 * 40 password/tls on port 587 to everybody
		 * 20 no auth on port 25 to everybody
		 *
		 * @param unknown $hostData        	
		 */
		public function getConfigurationRecommendation($hostData) {
			$port = $hostData ['port'];
			$hostname = $hostData ['host'];
			$oauthPotential = $this->isServiceProviderGoogle ( $hostname ) || $this->isServiceProviderMicrosoft ( $hostname ) || $this->isServiceProviderYahoo ( $hostname );
			if ($oauthPotential && $port == 465) {
				$recommendation ['priority'] = 100;
				$recommendation ['success'] = true;
				$recommendation ['message'] = __ ( 'Postman recommends OAuth 2.0 configuration on port 465.' );
				$recommendation ['auth'] = PostmanOptions::AUTHENTICATION_TYPE_OAUTH2;
				$recommendation ['enc'] = PostmanOptions::ENCRYPTION_TYPE_SSL;
				$recommendation ['port'] = 465;
			} else if ($oauthPotential && $port == 587) {
				$recommendation ['priority'] = 80;
				$recommendation ['success'] = true;
				$recommendation ['auth'] = PostmanOptions::AUTHENTICATION_TYPE_PLAIN;
				$recommendation ['enc'] = PostmanOptions::ENCRYPTION_TYPE_TLS;
				$recommendation ['port'] = 587;
				$recommendation ['transport'] = self::SLUG;
			} else if ($port == 465) {
				$recommendation ['priority'] = 60;
				$recommendation ['success'] = true;
				$recommendation ['message'] = __ ( 'Postman recommends Password configuration with SSL Security on port 465.' );
				$recommendation ['auth'] = PostmanOptions::AUTHENTICATION_TYPE_PLAIN;
				$recommendation ['enc'] = PostmanOptions::ENCRYPTION_TYPE_SSL;
				$recommendation ['port'] = 465;
				$recommendation ['transport'] = self::SLUG;
			} else if ($port == 587) {
				$recommendation ['priority'] = 40;
				$recommendation ['success'] = true;
				$recommendation ['message'] = __ ( 'Postman recommends Password configuration with TLS Security on port 587.' );
				$recommendation ['auth'] = PostmanOptions::AUTHENTICATION_TYPE_PLAIN;
				$recommendation ['enc'] = PostmanOptions::ENCRYPTION_TYPE_TLS;
				$recommendation ['port'] = 587;
				$recommendation ['transport'] = self::SLUG;
			} else {
				$recommendation ['priority'] = 20;
				$recommendation ['success'] = true;
				$recommendation ['message'] = sprintf ( __ ( 'Postman recommends no authentication on port %d.' ), $port );
				$recommendation ['auth'] = PostmanOptions::AUTHENTICATION_TYPE_NONE;
				$recommendation ['enc'] = PostmanOptions::ENCRYPTION_TYPE_NONE;
				$recommendation ['port'] = $port;
				$recommendation ['transport'] = self::SLUG;
			}
			$transportDescription = $this->getTransportDescription ( $recommendation ['enc'] );
			$encType = strtoupper ( $recommendation ['enc'] );
			$authDesc = $this->getAuthenticationDescription ( $recommendation ['auth'] );
			$recommendation ['message'] = sprintf ( __ ( 'Postman recommends %1$s with %2$s authentication on port %3$d.' ), $transportDescription, $authDesc, $port );
			return $recommendation;
		}
	}
}

if (! class_exists ( 'PostmanDummyTransport' )) {
	class PostmanDummyTransport implements PostmanTransport {
		const UNCONFIGURED = 'unconfigured';
		private $logger;
		public function __construct() {
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		const SLUG = 'smtp';
		public function isSmtp() {
			return false;
		}
		public function isServiceProviderGoogle($hostname) {
			return endsWith ( $hostname, 'gmail.com' );
		}
		public function isServiceProviderMicrosoft($hostname) {
			return endsWith ( $hostname, 'live.com' );
		}
		public function isServiceProviderYahoo($hostname) {
			return endsWith ( $hostname, 'yahoo.com' );
		}
		public function isOAuthUsed($authType) {
			return false;
		}
		public function isTranscriptSupported() {
			return false;
		}
		public function getSlug() {
		}
		public function getName() {
		}
		public function createZendMailTransport($hostname, $config) {
		}
		public function getDeliveryDetails(PostmanOptionsInterface $options) {
		}
		public function isConfigured(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			return false;
		}
		public function isReady(PostmanOptionsInterface $options, PostmanOAuthToken $token) {
			return false;
		}
		public function getMisconfigurationMessage(PostmanConfigTextHelper $scribe, PostmanOptionsInterface $options, PostmanOAuthToken $token) {
		}
		public function getHostsToTest($hostname) {
		}
		public function getConfigurationRecommendation($hostData) {
		}
	}
}


