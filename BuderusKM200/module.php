<?php

declare(strict_types=1);

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

        $this->RegisterPropertyString('gateway_password', '');
        $this->RegisterPropertyString('private_password', '');
        /*
        $this->RegisterPropertyString('calculated_key', '');
         */

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

        $this->MaintainVariable('Notifications', $this->Translate('Notifications'), VARIABLETYPE_STRING, '~HTMLBox', $vpos++, true);
        $this->MaintainVariable('LastUpdate', $this->Translate('Last update'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetTimerInterval('UpdateData', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        $status = IS_ACTIVE;

        $host = $this->ReadPropertyString('host');
        $port = $this->ReadPropertyInteger('port');
        if ($host == '' || $port == 0) {
            $status = IS_INVALIDCONFIG;
        }

        /*
        $calculated_key = $this->ReadPropertyString('calculated_key');
        if ($calculated_key == '') {
            $status = IS_INVALIDCONFIG;
        }
         */

        $gateway_password = $this->ReadPropertyString('gateway_password');
        $private_password = $this->ReadPropertyString('private_password');

        if ($gateway_password == '' || $private_password == '') {
            $status = IS_INVALIDCONFIG;
        }

        $this->SetStatus($status);
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
        $formElements[] = ['type' => 'Label', 'caption' => 'Buderus KM200'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'host', 'caption' => 'Host'];
        $formElements[] = ['type' => 'NumberSpinner', 'name' => 'port', 'caption' => 'Port'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'gateway_password', 'caption' => 'Gateway password'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'private_password', 'caption' => 'Private password'];
        /*
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'calculated_key', 'caption' => 'Calculated key'];
         */

        $columns = [];
        $columns[] = [
            'caption' => 'Datapoint',
            'name'    => 'datapoint',
            'add'     => '',
            'width'   => 'auto',
            'edit'    => [
                'type' => 'ValidationTextBox'
            ]
        ];
        $columns[] = [
            'caption' => 'Variable type',
            'name'    => 'vartype',
            'add'     => VARIABLETYPE_FLOAT,
            'width'   => '150px',
            'edit'    => [
                'type'    => 'Select',
                'options' => [
                    ['caption' => 'Boolean', 'value' => VARIABLETYPE_BOOLEAN],
                    ['caption' => 'Integer', 'value' => VARIABLETYPE_INTEGER],
                    ['caption' => 'Float', 'value' => VARIABLETYPE_FLOAT],
                    ['caption' => 'String', 'value' => VARIABLETYPE_STRING],
                ]
            ]
        ];
        $formElements[] = ['type' => 'List', 'name' => 'fields', 'caption' => 'Fields', 'rowCount' => 15, 'add' => true, 'delete' => true, 'columns' => $columns];

        $formElements[] = ['type' => 'SelectScript', 'name' => 'convert_script', 'caption' => 'convert values'];

        $formElements[] = ['type' => 'Label', 'caption' => 'Update data every X minutes'];
        $formElements[] = ['type' => 'NumberSpinner', 'name' => 'update_interval', 'caption' => 'Minutes'];

        $formActions = [];
        $formActions[] = ['type' => 'Button', 'caption' => 'Verify access', 'onClick' => 'BuderusKM200_VerifyAccess($id);'];
        $formActions[] = ['type' => 'Button', 'caption' => 'Update data', 'onClick' => 'BuderusKM200_UpdateData($id);'];
        $formActions[] = ['type' => 'Button', 'caption' => 'Datapoint-sheet', 'onClick' => 'BuderusKM200_DatapointSheet($id);'];

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
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $now = time();

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

        $result = $this->GetData('/notifications');
        $this->SendDebug(__FUNCTION__, 'notifications=' . print_r($result, true), 0);
        if ($result == false) {
            $html = $this->Translate('Unable to get notifications');
        } else {
            $html = '<html>' . PHP_EOL;
            $html .= '<body>' . PHP_EOL;
            $html .= '<style>' . PHP_EOL;
            $html .= 'body { margin: 1; padding: 0; font-family: "Open Sans", sans-serif; font-size: 20px; }' . PHP_EOL;
            $html .= 'table { border-collapse: collapse; border: 0px solid; margin: 0.5em;}' . PHP_EOL;
            $html .= 'th, td { padding: 1; }' . PHP_EOL;
            $html .= 'thead, tdata { text-align: left; }' . PHP_EOL;
            $html .= '#spalte_zeitpunkt { width: 125px; }' . PHP_EOL;
            $html .= '#spalte_text { }' . PHP_EOL;
            $html .= '</style>' . PHP_EOL;
            if (count($result['values']) == 0) {
                $html .= $this->Translate('There are no notifications') . PHP_EOL;
            } else {
                $html .= '<table>' . PHP_EOL;
                $html .= '<colgroup><col id="spalte_zeitpunkt"></colgroup>' . PHP_EOL;
                $html .= '<colgroup><col id="spalte_text"></colgroup>' . PHP_EOL;
                $html .= '<thead>' . PHP_EOL;
                $html .= '<tr>' . PHP_EOL;
                $html .= '<th>Zeitpunkt</th>' . PHP_EOL;
                $html .= '<th>Meldung</th>' . PHP_EOL;
                $html .= '</tr>' . PHP_EOL;
                $html .= '</thead>' . PHP_EOL;
                $html .= '<tdata>' . PHP_EOL;
                foreach ($result['values'] as $item) {
                    $this->SendDebug(__FUNCTION__, ' ... notification=' . print_r($item, true), 0);
                    $ts = $this->GetArrayElem($item, 't', '');
                    $errorCode = $this->GetArrayElem($item, 'dcd', '?');
                    $addCode = $this->GetArrayElem($item, 'ccd', '?');
                    $classCode = $this->GetArrayElem($item, 'cat', '?');
                    $s = $errorCode . ' ' . $addCode . ' ' . $classCode;
                    $html .= '<tr>' . PHP_EOL;
                    $html .= '<td>' . $ts . '</td>' . PHP_EOL;
                    $html .= '<td>' . $s . '</td>' . PHP_EOL;
                    $html .= '</tr>' . PHP_EOL;
                }
            }
            $html .= '</tdata>' . PHP_EOL;
            $html .= '</table>' . PHP_EOL;
        }
        $html .= '</body>' . PHP_EOL;
        $html .= '</html>' . PHP_EOL;

        $this->SetValue('Notifications', $html);

        $this->SetValue('LastUpdate', $now);
        $this->SetStatus(IS_ACTIVE);

        $dur = time() - $now;
        $min = $this->ReadPropertyInteger('update_interval');
        $sec = $min * 60 - $dur;
        // minimal 50% Pause eines Update-Zyklus
        if ($sec < ($min * 60 / 2)) {
            $sec = $min * 60;
        }
        $this->SendDebug(__FUNCTION__, 'set timer to ' . $sec . 's', 0);
        $this->SetTimerInterval('UpdateData', $sec * 1000);
    }

    public function VerifyAccess()
    {
        if ($this->GetStatus() == IS_INACTIVE) {
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
        $this->SendDebug(__FUNCTION__, 'service=' . $service . ', jdata=' . print_r($jdata, true), 0);
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
                        $ent['range'] = implode(', ', $r);
                    }
                }
                break;
            case 'stringValue':
                $ent['type'] = $jdata['type'];
                $ent['value'] = $jdata['value'];
                if (isset($jdata['allowedValues'])) {
                    $ent['range'] = implode(', ', $jdata['allowedValues']);
                }
                break;
            case 'errorList':
                break;
            case 'switchProgram':
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
            $ent['recordable'] = isset($jdata['recordable']) && $jdata['recordable'] ? true : false;
            $entList[] = $ent;
        }
    }

    public function DatapointSheet()
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            echo $this->Translate('Instance is inactive') . PHP_EOL;
            return;
        }

        $urls = ['/gateway', '/system', '/heatSources', '/heatingCircuits', '/solarCircuits', '/dhwCircuits'];

        $this->SendDebug(__FUNCTION__, 'urls=' . print_r($urls, true), 0);

        $entList = [];
        foreach ($urls as $url) {
            $this->traverseService($url, $entList);
        }
        $this->SendDebug(__FUNCTION__, 'entList=' . print_r($entList, true), 0);

        $title = [];
        $title[] = $this->Translate('Name');
        $title[] = $this->Translate('Type');
        $title[] = $this->Translate('Unit');
        $title[] = $this->Translate('writeable?');
        $title[] = $this->Translate('recordable?');
        $title[] = $this->Translate('current value');
        $title[] = $this->Translate('possible values');
        $buf = implode(';', $title) . PHP_EOL;

        foreach ($entList as $ent) {
            $row = [];
            $row[] = '"' . $ent['name'] . '"';
            $row[] = $ent['type'];
            $row[] = isset($ent['unit']) ? '"' . $ent['unit'] . '"' : '';
            $row[] = isset($ent['writeable']) && $ent['writeable'] ? 'Ja' : 'Nein';
            $row[] = isset($ent['recordable']) && $ent['recordable'] ? 'Ja' : 'Nein';
            $row[] = isset($ent['value']) ? '"' . $ent['value'] . '"' : '';
            $row[] = isset($ent['range']) ? '"' . $ent['range'] . '"' : '';
            $buf .= implode(';', $row) . PHP_EOL;
        }

        $mediaName = $this->Translate('Buderus KM200 Datapoints');
        @$mediaID = IPS_GetMediaIDByName($mediaName, $this->InstanceID);
        if ($mediaID == false) {
            $mediaID = IPS_CreateMedia(MEDIATYPE_DOCUMENT);
            $filename = 'media' . DIRECTORY_SEPARATOR . $this->InstanceID . '.csv';
            IPS_SetMediaFile($mediaID, $filename, false);
            IPS_SetName($mediaID, $mediaName);
            IPS_SetParent($mediaID, $this->InstanceID);
        }
        IPS_SetMediaContent($mediaID, base64_encode($buf));
        $this->SendDebug(__FUNCTION__, 'mediaName=' . $mediaName . ', mediaID=' . $mediaID, 0);
    }

    private function GetKey()
    {
        /*
        $calculated_key = $this->ReadPropertyString('calculated_key');
        if ($calculated_key != '')
            return $calculated_key;
         */

        $gateway_password = $this->ReadPropertyString('gateway_password');
        $private_password = $this->ReadPropertyString('private_password');

        // Achtung: Gerätepasswort ohne Bindestriche
        $gateway_password = preg_replace('/-/', '', $gateway_password);

        // Salt der MD5-Hashes zur AES-Schlüsselerzeugung
        $crypt_md5_salt = pack(
                'c*',
                0x86, 0x78, 0x45, 0xe9, 0x7c, 0x4e, 0x29, 0xdc,
                0xe5, 0x22, 0xb9, 0xa7, 0xd3, 0xa3, 0xe0, 0x7b,
                0x15, 0x2b, 0xff, 0xad, 0xdd, 0xbe, 0xd7, 0xf5,
                0xff, 0xd8, 0x42, 0xe9, 0x89, 0x5a, 0xd1, 0xe4
            );

        // Erste Hälfte des Schlüssels: MD5 von ( Gerätepasswort . Salt )
        $key_1 = md5($gateway_password . $crypt_md5_salt, true);
        // Zweite Hälfte des Schlüssels - initial: MD5 von ( Salt )
        $key_2_initial = md5($crypt_md5_salt, true);
        // Zweite Hälfte des Schlüssels - privat: MD5 von ( Salt . privates Passwort )
        $key_2_private = md5($crypt_md5_salt . $private_password, true);

        $crypt_key_private = bin2hex($key_1 . $key_2_private);

        // $this->SendDebug(__FUNCTION__, 'gateway_password=' . $gateway_password . ', private_password=' . $private_password . ' => ' . $crypt_key_private, 0);
        return $crypt_key_private;
    }

    private function Encrypt($encryptData)
    {
        $key = $this->GetKey();

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
        $key = $this->GetKey();

        $decrypt = openssl_decrypt(
            base64_decode($decryptData),
            'aes-256-ecb',
            hex2bin($key),
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
        );

        if ($decrypt == '') {
            return false;
        }
        $decrypt = rtrim($decrypt, "\x00");
        if ($decrypt == '') {
            return false;
        }
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

    public function GetData(string $datapoint)
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
            'http://' . $host . ':' . $port . $datapoint,
            false,
            $context
        );
        $this->SendDebug(__FUNCTION__, 'options=' . print_r($options, true), 0);
        if ($content == false) {
            return false;
        }
        $data = $this->Decrypt($content);
        if ($data == false) {
            return false;
        }
        $this->SendDebug(__FUNCTION__, 'decrypt content=' . print_r($data, true), 0);
        return json_decode($data, true);
    }

    public function SetData(string $datapoint, string $Value)
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
                'header'     => "Content-type: application/json\r\n" .
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
