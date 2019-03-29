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
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'key', 'caption' => 'Key'];

        $formElements[] = ['type' => 'Label', 'label' => 'Update data every X minutes'];
        $formElements[] = ['type' => 'NumberSpinner', 'name' => 'update_interval', 'caption' => 'Minutes'];

        $formActions = [];
        $formActions[] = ['type' => 'Button', 'label' => 'Verify access', 'onClick' => 'BuderusKM200_VerifyAccess($id);'];
        $formActions[] = ['type' => 'Button', 'label' => 'Update data', 'onClick' => 'BuderusKM200_UpdateData($id);'];
        $formActions[] = ['type' => 'Button', 'label' => 'Datapoint-sheet', 'onClick' => 'BuderusKM200_DatapointSheet($id);'];
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
            echo $this->Translate('Instance is inactive') . PHP_EOL;
            return;
        }

        $msg = '';
        $r = $this->GetData('/system/healthStatus');
        if ($r == false) {
            $msg = $this->Translate('access failed') . ':' . PHP_EOL;
        } else {
            $msg = $this->Translate('access ok') . ':' . PHP_EOL;
            $msg .= '  ' . $this->Translate('system health') . ': ' . $r['value'] . PHP_EOL;
            $r = $this->GetData('/gateway/DateTime');
            if ($r != false) {
                $ts = strtotime($r['value']);
                $msg .= '  ' . $this->Translate('system time') . ': ' . date('d.m.Y H:i:s', $ts) . PHP_EOL;
            }
        }

        echo $msg;
    }

    private function traverseService($service, &$entList)
    {
        $jdata = $this->GetData($service);
        if ($jdata == false) {
            return;
        }

        $ent = [];

        switch ($jdata['type']) {
        case 'refEnum':
            foreach ($jdata['references'] as $subService) {
                $this->traverseService($subService['id'], $entList);
            }
                break;
        case 'moduleList':
                foreach ($jdata['values'] as $Modules) {
                    echo $jdata['type'] . '=' . print_r($Modules, true) . PHP_EOL;
                }
                break;
        case 'floatValue':
                $ent['type'] = $jdata['type'];
                $ent['value'] = $jdata['value'];
                $ent['unit'] = $jdata['unitOfMeasure'];
                //echo $jdata['type'] . ' ' . print_r($jdata, true) . PHP_EOL;
                if (isset($jdata['minValue']) && isset($jdata['maxValue'])) {
                    $ent['range'] = $jdata['minValue'] . ' ... ' . $jdata['maxValue'];
                } elseif (isset($jdata['minValue'])) {
                    $ent['range'] = $jdata['minValue'] . ' ... ';
                } elseif (isset($jdata['maxValue'])) {
                    $ent['range'] = ' ... ' . $jdata['maxValue'];
                }
                if (isset($jdata['state'])) {
                    $r = [];
                    foreach ($jdata['state'] as $s) {
                        foreach ($s as $var => $val) {
                            $r[] = $var . '=' . $val;
                        }
                    }
                    if ($r != []) {
                        $ent['range'] = implode($r, ', ');
                    }
                }
                break;
        case 'stringValue':
                $ent['type'] = $jdata['type'];
                $ent['value'] = $jdata['value'];
                if (isset($jdata['allowedValues'])) {
                    $ent['range'] = implode($jdata['allowedValues'], ', ');
                }
                break;
            case 'switchProgram':
            case 'errorList':
            case 'systeminfo':
            case 'arrayData':
            case 'yRecording':
                $ent['type'] = $jdata['type'];
                $ent['value'] = ' --- ' . $this->Translate('not useable') . ' --- ';
                break;
            default:
                break;
        }
        if ($ent != []) {
            $ent['name'] = $service;
            $ent['writeable'] = isset($jdata['writeable']) && $jdata['writeable'] ? true : false;
            $entList[] = $ent;
        }
    }

    public function DatapointSheet()
    {
        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            echo $this->Translate('Instance is inactive') . PHP_EOL;
            return;
        }

        $urls = [
        '/gateway',
        '/system',
        '/heatSources',
        '/heatingCircuits',
        '/solarCircuits',
        '/dhwCircuits',
        /*
        '/notifications',
        '/recordings'
        */
    ];

        $entList = [];
        foreach ($urls as $url) {
            $this->traverseService($url, $entList);
        }

        $title = [];
        $title[] = $this->Translate('Name');
        $title[] = $this->Translate('Type');
        $title[] = $this->Translate('Unit');
        $title[] = $this->Translate('writeable?');
        $title[] = $this->Translate('current value');
        $title[] = $this->Translate('possible values');
        $buf = implode($title, ';') . PHP_EOL;

        foreach ($entList as $ent) {
            $row = [];
            $row[] = '"' . $ent['name'] . '"';
            $row[] = $ent['type'];
            $row[] = isset($ent['unit']) ? '"' . $ent['unit'] . '"' : '';
            $row[] = isset($ent['writeable']) && $ent['writeable'] ? 'Ja' : 'Nein';
            $row[] = isset($ent['value']) ? '"' . $ent['value'] . '"' : '';
            $row[] = isset($ent['range']) ? '"' . $ent['range'] . '"' : '';
            $buf .= implode($row, ';') . PHP_EOL;
        }

        $mediaName = $this->Translate('Buderus KM200 Datapoints');
        @$mediaID = IPS_GetMediaIDByName($mediaName, $this->InstanceID);
        if ($mediaID == false) {
            $mediaID = IPS_CreateMedia(MEDIATYPE_DOCUMENT);
            $filename = 'media' . DIRECTORY_SEPARATOR . 'Buderus_KM200.csv';
            IPS_SetMediaFile($mediaID, $filename, false);
            IPS_SetName($mediaID, $mediaName);
            IPS_SetParent($mediaID, $this->InstanceID);
        }
        IPS_SetMediaContent($mediaID, base64_encode($buf));
    }

    private function Encrypt($encryptData)
    {
        $key = $this->ReadPropertyString('key');

        $blocksize = 16;
        $encrypt_padchar = $blocksize - (strlen($encryptData) % $blocksize);
        $encryptData .= str_repeat(chr($encrypt_padchar), $encrypt_padchar);

        return base64_encode(
            openssl_encrypt(
                $encryptData,
                'aes-256-ecb',
                hex2bin($key),
                OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
            )
        );
    }

    private function Decrypt($decryptData)
    {
        $key = $this->ReadPropertyString('key');
        $this->SendDebug(__FUNCTION__, 'key=' . $key, 0);

        $decrypt = openssl_decrypt(
            base64_decode($decryptData),
            'aes-256-ecb',
            hex2bin($key),
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
        );

        $decrypt = rtrim($decrypt, "\x00");
        $decrypt_len = strlen($decrypt);
        $decrypt_padchar = ord($decrypt[$decrypt_len - 1]);
        for ($i = 0; $i < $decrypt_padchar; $i++) {
            if ($decrypt_padchar != ord($decrypt[$decrypt_len - $i - 1])) {
                break;
            }
        }
        if ($i != $decrypt_padchar) {
            return $decrypt;
        } else {
            return substr(
                $decrypt,
                0,
                $decrypt_len - $decrypt_padchar
            );
        }
    }

    private function GetData($url)
    {
        $host = $this->ReadPropertyString('host');
        $port = $this->ReadPropertyInteger('port');

        $options = [
            'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\n" .
                        "User-Agent: TeleHeater/2.2.3\r\n"
            ]
        ];
        $context = stream_context_create($options);
        $content = @file_get_contents(
            'http://' . $host . ':' . $post . $url,
            false,
            $context
        );
        $this->SendDebug(__FUNCTION__, 'options=' . print_r($options, true), 0);
        $this->SendDebug(__FUNCTION__, 'content=' . print_r($content, true), 0);
        if ($content == false) {
            return false;
        }
        $data = $this->Decrypt($content);
        $this->SendDebug(__FUNCTION__, 'decrypt content=' . print_r($data, true), 0);
        return json_decode($data, true);
    }

    private function SetData($url, $Value)
    {
        $host = $this->ReadPropertyString('host');
        $port = $this->ReadPropertyInteger('port');

        $content = json_encode(
            [
                'value' => $Value
            ]
        );
        $options = [
            'http' => [
            'method'     => 'PUT',
                'header' => "Content-type: application/json\r\n" .
                            "User-Agent: TeleHeater/2.2.3\r\n",
                'content' => $this->Encrypt($content)
            ]
        ];
        $context = stream_context_create($options);
        @file_get_contents(
            'http://' . $host . ':' . $port . $url,
            false,
            $context
        );
    }
}
