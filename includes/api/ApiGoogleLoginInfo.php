<?php
class ApiGoogleLoginInfo extends ApiBase {
	public function execute() {
		$apiResult = $this->getResult();
		$params = $this->extractRequestParams();
		$glConfig = ConfigFactory::getDefaultInstance()->makeConfig( 'googlelogin' );
		$user = $this->getUser();

		if ( !isset( $params['googleid'] ) ) {
			$this->dieUsage( 'Invalid Google ID', 'googleidinvalid' );
		}

		// only user with the managegooglelogin right can use this Api
		if ( !$user->isAllowed( 'managegooglelogin' ) ) {
			$this->dieUsage(
				'Insufficient permissions. You need the managegooglelogin permission to use this APi module',
				'insufficientpermissions'
			);
		}

		// check, if the api is protected and if the key is correct
		$plusCheck = Http::get(
			'https://www.googleapis.com/plus/v1/people/' .
			$params['googleid'] .
			'?key=' .
			$glConfig->get( 'GLAPIKey' )
		);
		
		if ( !$plusCheck ) {
			$this->dieUsage( 'Google user not found or false api key.', 'unknownuser' );
		}
		$plusCheck = json_decode( $plusCheck, true );
		$result = array();
		if ( $plusCheck['displayName'] ) {
			$result['displayName'] = $plusCheck['displayName'];
		}
		if ( $plusCheck['image'] ) {
			$result['profileimage'] = $plusCheck['image']['url'];
		}
		if ( $plusCheck['isPlusUser'] ) {
			$result['isPlusUser'] = $plusCheck['isPlusUser'];
		}
		if ( is_array( $plusCheck['organizations'] ) ) {
			$org = $plusCheck['organizations'][0];
			if ( $org['primary'] ) {
				$result['orgName'] = $org['name'];
			}
			if ( $org['title'] ) {
				$result['orgTitle'] = $org['title'];
			}
			if ( $org['startDate'] ) {
				$result['orgSince'] = $org['startDate'];
			}
		}
		// build result array
		$r = array(
			'success' => true,
			'result' => $result
		);
		// add result to API output
		$apiResult->addValue( null, $this->getModuleName(), $r );
	}

	public function getAllowedParams() {
		return array(
			'googleid' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
		);
	}
}
