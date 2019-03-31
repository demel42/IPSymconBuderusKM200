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

        $fields = [
                [
                    'datapoint'  => '/system/healthStatus',
                    'vartype'    => VARIABLETYPE_INTEGER,
                ],
            ];
        $this->RegisterPropertyString('fields', json_encode($fields));

        $this->RegisterPropertyInteger('convert_script', 0);

        $this->RegisterPropertyInteger('update_interval', '60');

        $this->RegisterTimer('UpdateData', 0, 'BuderusKM200_UpdateData(' . $this->InstanceID . ');');

        $associations = [
                    ['Wert' => false, 'Name' => $this->Translate('Stop'), 'Farbe' => -1],
                    ['Wert' => true, 'Name' => $this->Translate('Start'), 'Farbe' => -1],
                ];
        $this->CreateVarProfile('BuderusKM200.Charge', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 1, '', $associations);
        $associations = [
                    ['Wert' => false, 'Name' => $this->Translate('Off'), 'Farbe' => -1],
                    ['Wert' => true, 'Name' => $this->Translate('On'), 'Farbe' => -1],
                ];
        $this->CreateVarProfile('BuderusKM200.OnOff', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 1, '', $associations);

        $associations = [
                    ['Wert' => 0, 'Name' => $this->Translate('Off'), 'Farbe' => -1],
                    ['Wert' => 1, 'Name' => $this->Translate('High'), 'Farbe' => -1],
                    ['Wert' => 1, 'Name' => $this->Translate('HC-Program'), 'Farbe' => -1],
                    ['Wert' => 1, 'Name' => $this->Translate('Own program'), 'Farbe' => -1],
                ];
        $this->CreateVarProfile('BuderusKM200.Dwh_OperationMode', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations);
        $associations = [
                    ['Wert' => 0, 'Name' => $this->Translate('automatic'), 'Farbe' => -1],
                    ['Wert' => 1, 'Name' => $this->Translate('manual'), 'Farbe' => -1],
                ];
        $this->CreateVarProfile('BuderusKM200.Hc_OperationMode', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations);
        $associations = [
                    ['Wert' => 0, 'Name' => $this->Translate('Error'), 'Farbe' => 0xEE0000],
                    ['Wert' => 1, 'Name' => $this->Translate('Maintenance'), 'Farbe' => 0xFFFF00],
                    ['Wert' => 2, 'Name' => $this->Translate('Ok'), 'Farbe' => 0x228B22],
                ];
        $this->CreateVarProfile('BuderusKM200.HealthStatus', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations);
        $this->CreateVarProfile('BuderusKM200.min', VARIABLETYPE_INTEGER, ' min', 0, 0, 0, 0, '');
        $associations = [
                    ['Wert' => 0, 'Name' => $this->Translate('Inactive'), 'Farbe' => -1],
                    ['Wert' => 1, 'Name' => $this->Translate('Active'), 'Farbe' => -1],
                ];
        $this->CreateVarProfile('BuderusKM200.Status', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations);

        $this->CreateVarProfile('BuderusKM200.bar', VARIABLETYPE_FLOAT, ' bar', 0, 0, 0, 0, '');
        $associations = [
                    ['Wert' => -3276.8, 'Name' => '-', 'Farbe' => -1],
                    ['Wert' => -3276.7, 'Name' => '%.1f °C', 'Farbe' => -1],
                ];
        $this->CreateVarProfile('BuderusKM200.Celsius', VARIABLETYPE_FLOAT, '', 0, 0, 0, 1, 'Temperature', $associations);
        $this->CreateVarProfile('BuderusKM200.kWh', VARIABLETYPE_FLOAT, ' kWh', 0, 0, 0, 0, '');
        $this->CreateVarProfile('BuderusKM200.kW', VARIABLETYPE_FLOAT, ' kW', 0, 0, 0, 1, '');
        $this->CreateVarProfile('BuderusKM200.l_min', VARIABLETYPE_FLOAT, ' l/min', 0, 0, 0, 0, '');
        $this->CreateVarProfile('BuderusKM200.Pascal', VARIABLETYPE_FLOAT, ' Pascal', 0, 0, 0, 0, '');
        $this->CreateVarProfile('BuderusKM200.Percent', VARIABLETYPE_FLOAT, ' %', 0, 0, 0, 0, '');
        $this->CreateVarProfile('BuderusKM200.Wh', VARIABLETYPE_FLOAT, ' Wh', 0, 0, 0, 0, '');
    }

    private function findVariables($objID, &$objList)
    {
        $chldIDs = IPS_GetChildrenIDs($objID);
        foreach ($chldIDs as $chldID) {
            $obj = IPS_GetObject($chldID);
            switch ($obj['ObjectType']) {
                case OBJECTTYPE_VARIABLE:
                    if (substr($obj['ObjectIdent'], 0, 3) == 'DP_') {
                        $objList[] = $obj;
                    }
                    break;
                case OBJECTTYPE_CATEGORY:
                    $this->findVariables($chldID, $objList);
                    break;
                default:
                    break;
            }
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $fields = json_decode($this->ReadPropertyString('fields'), true);

        $vpos = 1000;

        $varList = [];
        foreach ($fields as $field) {
            $this->SendDebug(__FUNCTION__, 'field=' . print_r($field, true), 0);
            $datapoint = $this->GetArrayElem($field, 'datapoint', '');
            $vartype = $this->GetArrayElem($field, 'vartype', -1);

            if ($datapoint == '' || $vartype == -1) {
                $this->SendDebug(__FUNCTION__, 'ignore field: datapoint=' . $datapoint . ', vartype=' . $vartype, 0);
                continue;
            }

            $ident = 'DP' . str_replace('/', '_', $datapoint);

            $this->SendDebug(__FUNCTION__, 'register variable: ident=' . $ident . ', vartype=' . $vartype, 0);

            $this->MaintainVariable($ident, $datapoint, $vartype, '', $vpos++, true);
            $varList[] = $ident;
        }

        $this->SendDebug(__FUNCTION__, 'varList=' . print_r($varList, true), 0);

        $objList = [];
        $this->findVariables($this->InstanceID, $objList);
        foreach ($objList as $obj) {
            $ident = $obj['ObjectIdent'];
            if (!in_array($ident, $varList)) {
                $this->SendDebug(__FUNCTION__, 'unregister variable: ident=' . $ident, 0);
                $this->UnregisterVariable($ident);
            }
        }

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

        $columns = [];
        $columns[] = ['caption' => 'Datapoint', 'name' => 'datapoint', 'add' => '', 'width' => 'auto', 'edit' => [
                                'type' => 'ValidationTextBox'
                            ]
                        ];
        $options = [
                ['caption' => 'Boolean', 'value' => VARIABLETYPE_BOOLEAN],
                ['caption' => 'Integer', 'value' => VARIABLETYPE_INTEGER],
                ['caption' => 'Float', 'value' => VARIABLETYPE_FLOAT],
                ['caption' => 'String', 'value' => VARIABLETYPE_STRING],
            ];
        $columns[] = ['caption' => 'Variable type', 'name' => 'vartype', 'add' => VARIABLETYPE_FLOAT, 'width' => '150px', 'edit' => [
                                'caption' => 'Field', 'type' => 'Select', 'name' => 'field', 'options' => $options
                            ]
                        ];
        $formElements[] = ['type' => 'List', 'name' => 'fields', 'caption' => 'Fields', 'rowCount' => 15, 'add' => true, 'delete' => true, 'columns' => $columns];

        $formElements[] = ['type' => 'SelectScript', 'name' => 'convert_script', 'caption' => 'convert values'];

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

        $convert_script = $this->ReadPropertyInteger('convert_script');

        $fields = json_decode($this->ReadPropertyString('fields'), true);
        foreach ($fields as $field) {
            $datapoint = $this->GetArrayElem($field, 'datapoint', '');
            $result = $this->GetData($datapoint);

            $vartype = $this->GetArrayElem($field, 'vartype', -1);
            $type = $this->GetArrayElem($result, 'type', '');
            $value = $this->GetArrayElem($result, 'value', '');

            $do_convert = false;
            if ($type == 'floatValue' && $vartype != VARIABLETYPE_FLOAT) {
                $do_convert = true;
            }
            if ($type == 'stringValue' && $vartype != VARIABLETYPE_STRING) {
                $do_convert = true;
            }

            $this->SendDebug(__FUNCTION__, 'datapoint=' . $datapoint . ', result=' . print_r($result, true), 0);

            if ($do_convert && $convert_script > 0) {
                $info = [
                        'InstanceID'    => $this->InstanceID,
                        'datapoint'     => $datapoint,
                        'vartype'       => $vartype,
                        'value'         => $value,
                        'result'        => json_encode($result),
                    ];
                $r = IPS_RunScriptWaitEx($convert_script, $info);
                $this->SendDebug(__FUNCTION__, 'convert: datapoint=' . $datapoint . ', orgval=' . $value . ', value=' . ($r == false ? '<nop>' : $r), 0);
                if ($r != false) {
                    $value = $r;
                    $do_convert = false;
                }
            }
            if ($do_convert) {
                switch ($vartype) {
                    case VARIABLETYPE_BOOLEAN:
                        if (isset($result['allowedValues'])) {
                            $orgval = $value;
                            $allowedValues = $result['allowedValues'];
                            foreach ($allowedValues as $val => $txt) {
                                if ($txt == $value) {
                                    $value = $val;
                                    break;
                                }
                            }
                            $this->SendDebug(__FUNCTION__, 'string2bool: datapoint=' . $datapoint . ', orgval=' . $orgval . ', value=' . $value . ', allowedValues=' . print_r($allowedValues, true), 0);
                        }
                        break;
                    case VARIABLETYPE_INTEGER:
                        switch ($datapoint) {
                            case '/gateway/DateTime':
                            case '/heatSources/energyMonitoring/startDateTime':
                                $value = strtotime($value);
                                break;
                            default:
                                if (isset($result['allowedValues'])) {
                                    $orgval = $value;
                                    $allowedValues = $result['allowedValues'];
                                    foreach ($allowedValues as $val => $txt) {
                                        if ($txt == $value) {
                                            $value = $val;
                                            break;
                                        }
                                    }
                                    $this->SendDebug(__FUNCTION__, 'string2int: datapoint=' . $datapoint . ', orgval=' . $orgval . ', value=' . $value . ', allowedValues=' . print_r($allowedValues, true), 0);
                                }
                                break;
                        }
                        break;
                }
            }

            $ident = 'DP' . str_replace('/', '_', $datapoint);
            $this->SetValue($ident, $value);
        }

        $this->SetValue('LastUpdate', time());
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
            $msg = $this->Translate('Access failed') . ':' . PHP_EOL;
        } else {
            $msg = $this->Translate('Access ok') . ':' . PHP_EOL;
            $msg .= '  ' . $this->Translate('System health') . ': ' . $r['value'] . PHP_EOL;
            $msg .= PHP_EOL;
            $r = $this->GetData('/system/brand');
            if ($r != false) {
                $msg .= '  ' . $this->Translate('Brand') . ': ' . $r['value'] . PHP_EOL;
            }
            $r = $this->GetData('/system/bus');
            if ($r != false) {
                $msg .= '  ' . $this->Translate('Bus') . ': ' . $r['value'] . PHP_EOL;
            }
            $r = $this->GetData('/system/systemType');
            if ($r != false) {
                $msg .= '  ' . $this->Translate('System type') . ': ' . $r['value'] . PHP_EOL;
            }
            $msg .= PHP_EOL;
            $r = $this->GetData('/gateway/versionHardware');
            if ($r != false) {
                $msg .= '  ' . $this->Translate('Hardware version') . ': ' . $r['value'] . PHP_EOL;
            }
            $r = $this->GetData('/gateway/versionFirmware');
            if ($r != false) {
                $msg .= '  ' . $this->Translate('Firmware version') . ': ' . $r['value'] . PHP_EOL;
            }
            $msg .= PHP_EOL;
            $r = $this->GetData('/gateway/DateTime');
            if ($r != false) {
                $ts = strtotime($r['value']);
                $msg .= '  ' . $this->Translate('System time') . ': ' . date('d.m.Y H:i:s', $ts) . PHP_EOL;
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

        $urls = ['/gateway', '/system', '/heatSources', '/heatingCircuits', '/solarCircuits', '/dhwCircuits'];

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

    public function GetData($datapoint)
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
            'http://' . $host . ':' . $post . $datapoint,
            false,
            $context
        );
        $this->SendDebug(__FUNCTION__, 'options=' . print_r($options, true), 0);
        if ($content == false) {
            return false;
        }
        $data = $this->Decrypt($content);
        $this->SendDebug(__FUNCTION__, 'decrypt content=' . print_r($data, true), 0);
        return json_decode($data, true);
    }

    public function SetData($datapoint, $Value)
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
            'http://' . $host . ':' . $port . $datapoint,
            false,
            $context
        );
    }
}
