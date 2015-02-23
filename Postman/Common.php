<?php
if (! class_exists ( "PostmanLogger" )) {
	
	require_once 'PostmanOptions.php';
	
	//
	class PostmanLogger {
		const ALL_INT = - 2147483648;
		const DEBUG_INT = 10000;
		const ERROR_INT = 40000;
		const FATAL_INT = 50000;
		const INFO_INT = 20000;
		const OFF_INT = 2147483647;
		const WARN_INT = 30000;
		private $name;
		private $logLevel;
		function __construct($name) {
			$this->name = $name;
			$this->logLevel = PostmanOptions::getInstance ()->getLogLevel ();
		}
		// TODO better logging http://www.smashingmagazine.com/2011/03/08/ten-things-every-wordpress-plugin-developer-should-know/
		function debug($text) {
			if (self::DEBUG_INT >= $this->logLevel) {
				error_log ( 'DEBUG ' . $this->name . ': ' . $text );
			}
		}
		function error($text) {
			if (self::ERROR_INT >= $this->logLevel) {
				error_log ( 'ERROR ' . $this->name . ': ' . $text );
			}
		}
	}
}

if (! interface_exists ( 'PostmanTransport' )) {
	interface PostmanTransport {
		public function isSmtp();
		public function isServiceProviderGoogle($hostname);
		public function isServiceProviderMicrosoft($hostname);
		public function isServiceProviderYahoo($hostname);
		public function isOAuthUsed($authType);
		public function isTranscriptSupported();
		public function getSlug();
		public function getName();
		public function createPostmanMailAuthenticator(PostmanOptions $options, PostmanOAuthToken $authToken);
		public function createZendMailTransport($hostname, $config);
		public function isConfigured(PostmanOptionsInterface $options, PostmanOAuthToken $token);
		public function isReady(PostmanOptionsInterface $options, PostmanOAuthToken $token);
		public function getMisconfigurationMessage(PostmanConfigTextHelper $scribe, PostmanOptionsInterface $options, PostmanOAuthToken $token);
		public function getConfigurationRecommendation($hostData);
		public function getHostsToTest($hostname);
	}
}

if (! class_exists ( 'PostmanTransportDirectory' )) {
	class PostmanTransportDirectory {
		private $transports;
		private $logger;
		/**
		 * private constructor
		 */
		private function __construct() {
			// add the default Transport
			$this->logger = new PostmanLogger ( get_class ( $this ) );
		}
		
		// singleton instance
		public static function getInstance() {
			static $inst = null;
			if ($inst === null) {
				$inst = new PostmanTransportDirectory ();
			}
			return $inst;
		}
		public function registerTransport(PostmanTransport $instance) {
			$this->logger->debug ( 'Registering ' . $instance->getName () . ' transport as ' . $instance->getSlug () );
			$this->transports [$instance->getSlug ()] = $instance;
		}
		public function getTransports() {
			return $this->transports;
		}
	}
}

