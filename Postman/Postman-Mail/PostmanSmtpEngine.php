<?php
if (! interface_exists ( "PostmanSmtpEngine" )) {
	interface PostmanSmtpEngine {
		
		// constants
		const ZEND_TRANSPORT_CONFIG_SSL = 'ssl';
		const ZEND_TRANSPORT_CONFIG_TLS = 'tls';
		const ZEND_TRANSPORT_CONFIG_PORT = 'port';
		
		/**
		 * Create the Zend transport configuration
		 *
		 * @param PostmanEmailAddress $sender        	
		 * @param unknown $hostname        	
		 * @param unknown $port        	
		 */
		public function createConfig(PostmanEmailAddress $sender, $hostname, $port);
	}
}