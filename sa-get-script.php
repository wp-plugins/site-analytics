<?php
	//*********************************************************************************************
	//
	//	Site Analytics Script Service
	//	(c)2009-2010 GoDaddy.com
	//
	//	email: suggestions at trafficfacts.com
	//
	//	This module attempts to get the JavaScript from the Site Analytics servers for inclusion
	//	on the site pages. It will return one of three things:
	//
	//	1. The latest script for this site.
	//		The script changes from time to time as enhancements are made. This service gets the
	//		latest version.
	//
	//	2. A message stating that the site could not be found on the Site Analytics site.
	//		The domain name must match exactly with the domain name at Site Analytics.
	//
	//	3. A message stating that the Site Analytics servers could not be contacted.
	//		It may be that the proxy server settings found in the "saGetScript( )" function need to
	//		be changed to match the proxy settings at your server. Some web hosts require all
	//		outgoing traffic from their shared servers be routed through a proxy. The two variables,
	//		$sa_proxy_server and $sa_proxy_port, contain the proxy server information. The default
	//		is 'proxy.shr.secureserver.net', port '3128', but this may be changed to match your setup.
	//		This information will be available from your hosting provider.
	//
	//*********************************************************************************************

	function sa_get_script( $siteurl, &$sa_proxy_error, &$sa_domain_not_found ) {
		$sa_proxy_server = 'proxy.shr.secureserver.net';
		$sa_proxy_port = '3128';
		$sa_webservice='webapp.trafficfacts.com';
		$sa_app='/webservices/TFRTScriptServiceText.php';

		if ( strtolower( substr( $siteurl, 0, 4 )) != 'http' ) {
			$siteurl = 'http://' . $siteurl;
		}
		$parts = parse_url( $siteurl );
		$sa_hostname = $parts['host'];
		$sa_use_proxy = false;
		$sa_domain_not_found = false;
		$sa_proxy_error = false;
		$script = '';
		if ( sa_ping_server( $sa_webservice, $sa_proxy_server, $sa_proxy_port, $sa_proxy_error, $sa_use_proxy ) === true ) {
			if ( substr( strtolower( $sa_hostname ), 0, 4 ) == 'www.' ) {
				$sa_hostname = substr( $sa_hostname, 4 );
			}
			$script = ltrim( sa_get_from_server( $sa_webservice, $sa_proxy_server, $sa_proxy_port, $sa_app, $sa_hostname, $sa_use_proxy, $sa_domain_not_found, $sa_proxy_error ));
		}
		if ( true === $sa_domain_not_found ) {
			$script = "We can't find a Site Analytics account for \"$sa_hostname\". You either have no Site Analytics account, ".
			"or it has a different domain name. See http://www.godaddy.com/hosting/website-analytics.aspx?isc=WPSA1 for details.";
		} else if ( true === $sa_proxy_error ) {
			$script = "We were not able to contact the Site Analytics server. Please go to the Site Analytics Control Panel, ".
			"Configuration option, and get the code for the appropriate page type. Paste that code here, replacing this text. ".
			"Do not alter the script. If you do not have a Site Analytics account, go to http://www.godaddy.com/hosting/website-analytics.aspx?isc=WPSA1 and look it over.";
		}
		return $script;
	}

	function sa_ping_server( $server, $proxyServer, $proxyPort, &$proxyError, &$useProxy ) {
		$rc = true;
		$proxyError = false;
		$useProxy = false;
		$errno = 0;
		$errstr ='';
		if (( $socket = @fsockopen( $server, 80, $errno, $errstr, 5 )) !== false ) {
			@fclose( $socket );
		} else {
			if (( false !== $proxyServer ) &&
				( '' !== $proxyServer ) &&
				( false !== $proxyPort ) &&
				( '' !== $proxyPort )) {
				$errno = 0;
				$errstr ='';
				if (( $socket = @fsockopen( $proxyServer, $proxyPort, $errno, $errstr, 5 )) !== false ) {
					@fclose( $socket );
					$useProxy = true;
				} else {
					$proxyError = true;
					$rc = false;
				}
			} else {
				$proxyError = true;
				$rc = false;
			}
		}
		return $rc;
	}

	function sa_unchunk( $payload ) {
		$result = '';
		while ( true ) {
			if (( $idx = strpos( $payload, "\r\n" )) !== false ) {
				$str = trim( substr( $payload, 0, $idx ));
				$payload = substr( $payload, $idx + 2 );
			} else
				break;
			$len = hexdec( $str );
			if ( $len > 0 ) {
				$result .= substr( $payload, 0, $len );
				$payload = ltrim( substr( $payload, $len + 1));
			} else
				break;
		}
		return $result;
	}

	function sa_get_from_server( $server, $proxyServer, $proxyPort, $app, $domain, $useProxy, &$domainNotFound, &$proxyError ) {
		$proxy_error = false;
		$domain_not_found = false;
		$timeout = 10;
		$result = false;
		$request = "GET $app?function=GetScriptLong&domain=$domain&secure=0 HTTP/1.1\r\nHost:$server\r\nConnection: Close\r\n\r\n";
		if ( true === $useProxy ) {
			$socket = @fsockopen( $proxyServer, $proxyPort, $errno, $errstr, $timeout );
		}
		else {
			$socket = @fsockopen( $server, 80, $errno, $errstr, $timeout );
		}
		if ( false === $socket ) {
			$proxyError = true;
		} else {
			@fwrite( $socket, $request );
			while ( $socket && !@feof( $socket )) {
				$result .= @fread( $socket, 1024 );
			}
			if ( $socket ) {
				@fclose( $socket );
			}
			$chunked = false;
			$returnCode = 0;
			if (( $idx = strpos( $result, "\r\n\r\n" )) !== false ) {
				$headers = explode( "\r\n", strtolower( substr( $result, 0, $idx + 4 )));
				if ( is_array( $headers )) {
					foreach ( $headers as $header ) {
						if ( $header === '' ) {
							break;
						}
						if ( substr( $header, 0, 4 ) == 'http' ) {
							$parts = explode( ' ', $header );
							$returnCode = $parts[1];
							continue;
						}
						$hdr_val = explode( ':', $header );
						if ( $hdr_val[0] == 'transfer-encoding' ) {
							if ( trim($hdr_val[1] ) == 'chunked' ) {
								$chunked = true;
								break;
							}
						}
					}
				}
				$result = trim( substr( $result, $idx + 4 ));
			}
			if ( $chunked ) {
				$result = sa_unchunk( $result );
			}
			if ( $returnCode != '200' ) {
				$result = false;
				$proxyError = true;
			}
			if ( strpos( $result, "not in Site Analytics database" ) !== false ) {
				$result = false;
				$domainNotFound = true;
			}
		}
		return $result;
	}
?>