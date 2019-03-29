<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

class BuderusKM200 extends IPSModule
{
    use BuderusKM200Common;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('host', '');
		$this->RegisterPropertyInteger('port', 80);

        $this->RegisterPropertyString('key', '');

        $this->RegisterPropertyInteger('update_interval', '60');

        $this->RegisterTimer('UpdateData', 0, 'BuderusKM200_UpdateData(' . $this->InstanceID . ');');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $vpos = 0;

        $vpos = 100;
        $this->MaintainVariable('LastUpdate', $this->Translate('Last update'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetTimerInterval('UpdateData', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        $host = $this->ReadPropertyString('host');
        $port = $this->ReadPropertyInteger('port');
        $key = $this->ReadPropertyString('key');
        if ($host == '' || $port == 0 || $key == '') {
            $this->SetStatus(IS_INVALIDCONFIG);
        } else {
            $this->SetStatus(IS_ACTIVE);
        }

        $this->SetUpdateInterval();
    }

    protected function SetUpdateInterval()
    {
        $min = $this->ReadPropertyInteger('update_interval');
        $msec = $min > 0 ? $min * 60 * 1000 : 0;
        $this->SetTimerInterval('UpdateData', $msec);
    }

    public function GetConfigurationForm()
    {
        $formElements = [];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'module_disable', 'caption' => 'Instance is disabled'];
        $formElements[] = ['type' => 'Label', 'label' => 'Buderus KM200'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'host', 'caption' => 'Host'];
		$formElements[] = ['type' => 'NumberSpinner', 'name' => 'port', 'caption' => 'Port'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'key', 'caption' => 'key'];

        $formElements[] = ['type' => 'Label', 'label' => 'Update status every X minutes'];
        $formElements[] = ['type' => 'NumberSpinner', 'name' => 'update_interval', 'caption' => 'Minutes'];

        $formActions = [];
        $formActions[] = ['type' => 'Button', 'label' => 'Verify access', 'onClick' => 'BuderusKM200_VerifyAccess($id);'];
        $formActions[] = ['type' => 'Button', 'label' => 'Update data', 'onClick' => 'BuderusKM200_UpdateData($id);'];
        $formActions[] = ['type' => 'Label', 'label' => '____________________________________________________________________________________________________'];
        $formActions[] = [
                            'type'    => 'Button',
                            'caption' => 'Module description',
                            'onClick' => 'echo "https://github.com/demel42/IPSymconBuderusKM200/blob/master/README.md";'
                        ];

        $formStatus = [];
        $formStatus[] = ['code' => IS_CREATING, 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => IS_ACTIVE, 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => IS_DELETING, 'icon' => 'inactive', 'caption' => 'Instance is deleted'];
        $formStatus[] = ['code' => IS_INACTIVE, 'icon' => 'inactive', 'caption' => 'Instance is inactive'];
        $formStatus[] = ['code' => IS_NOTCREATED, 'icon' => 'inactive', 'caption' => 'Instance is not created'];

        $formStatus[] = ['code' => IS_INVALIDCONFIG, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid configuration)'];

        return json_encode(['elements' => $formElements, 'actions' => $formActions, 'status' => $formStatus]);
    }

    public function UpdateData()
    {
        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $this->SetStatus(IS_ACTIVE);
    }

    public function VerifyAccess()
    {
        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            echo $this->translate('Instance is inactive') . PHP_EOL;
            return;
        }

        $msg = '';

        echo $msg;
    }

	private function Encrypt( $encryptData ) 
	{ 
		$key = $this->ReadPropertyString('key');

		$blocksize = 16;
		$encrypt_padchar = $blocksize - ( strlen( $encryptData ) % $blocksize ); 
		$encryptData .= str_repeat( chr( $encrypt_padchar ), $encrypt_padchar ); 
		
		return base64_encode(
			openssl_encrypt(
				$encryptData,
				"aes-256-ecb",
				$key,
				OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
			)
		);
	}

	private function Decrypt( $decryptData ) 
	{ 
		$key = $this->ReadPropertyString('key');

		$decrypt = openssl_decrypt( 
			base64_decode( $decryptData ),
			"aes-256-ecb",
			$key,
			OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
		);
			
		$decrypt = rtrim( $decrypt, "\x00" ); 
		$decrypt_len = strlen( $decrypt ); 
		$decrypt_padchar = ord( $decrypt[ $decrypt_len - 1 ] ); 
		for ( $i = 0; $i < $decrypt_padchar ; $i++ ) 
		{ 
			if ( $decrypt_padchar != ord( $decrypt[$decrypt_len - $i - 1] ) ) 
			break; 
		} 
		if ( $i != $decrypt_padchar ) 
			return $decrypt; 
		else 
			return substr( 
				$decrypt, 
				0, 
				$decrypt_len - $decrypt_padchar 
			); 
	}

	private function GetData( $url ) 
	{ 
		$host = $this->ReadPropertyString('host');
		$port = $this->ReadPropertyInteger('port');

		$options = array( 
			'http' => array( 
			'method' => "GET", 
			'header' => "Accept: application/json\r\n" . 
							"User-Agent: TeleHeater/2.2.3\r\n" 
			) 
		); 
		$context = stream_context_create( $options ); 
		$content = @file_get_contents( 
			'http://' . $host . ':' . $post . $url, 
			false, 
			$context 
		); 
		if ( false === $content ) 
			return false; 
		return json_decode( $this->Decrypt( $content ) ); 
	} 

	private function SetData( $url, $Value ) 
	{ 
		$host = $this->ReadPropertyString('host');
		$port = $this->ReadPropertyInteger('port');

		$content = json_encode( 
			array( 
				"value" => $Value 
			) 
		); 
		$options = array( 
			'http' => array( 
			'method' => "PUT", 
				'header' => "Content-type: application/json\r\n" . 
							"User-Agent: TeleHeater/2.2.3\r\n", 
				'content' => $this->Encrypt( $content ) 
			) 
		); 
		$context = stream_context_create( $options ); 
		@file_get_contents( 
			'http://' . $host . ':' . $port . $url, 
			false, 
			$context 
		); 
	}
}
