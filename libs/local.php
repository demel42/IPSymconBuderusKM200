<?php

declare(strict_types=1);

trait BuderusKM200LocalLib
{
    private function GetFormStatus()
    {
        $formStatus = $this->GetCommonFormStatus();

        return $formStatus;
    }

    public static $STATUS_INVALID = 0;
    public static $STATUS_VALID = 1;
    public static $STATUS_RETRYABLE = 2;

    private function CheckStatus()
    {
        switch ($this->GetStatus()) {
            case IS_ACTIVE:
                $class = self::$STATUS_VALID;
                break;
            default:
                $class = self::$STATUS_INVALID;
                break;
        }

        return $class;
    }

    public function InstallVarProfiles(bool $reInstall = false)
    {
        if ($reInstall) {
            $this->SendDebug(__FUNCTION__, 'reInstall=' . $this->bool2str($reInstall), 0);
        }

        $associations = [
            ['Wert' => false, 'Name' => $this->Translate('Stop'), 'Farbe' => -1],
            ['Wert' => true, 'Name' => $this->Translate('Start'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('BuderusKM200.Charge', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 1, '', $associations, $reInstall);

        $associations = [
            ['Wert' => false, 'Name' => $this->Translate('Off'), 'Farbe' => -1],
            ['Wert' => true, 'Name' => $this->Translate('On'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('BuderusKM200.OnOff', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 1, '', $associations, $reInstall);

        $associations = [
            ['Wert' => 0, 'Name' => $this->Translate('Off'), 'Farbe' => -1],
            ['Wert' => 1, 'Name' => $this->Translate('High'), 'Farbe' => -1],
            ['Wert' => 1, 'Name' => $this->Translate('HC-Program'), 'Farbe' => -1],
            ['Wert' => 1, 'Name' => $this->Translate('Own program'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('BuderusKM200.Dwh_OperationMode', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations, $reInstall);

        $associations = [
            ['Wert' => 0, 'Name' => $this->Translate('automatic'), 'Farbe' => -1],
            ['Wert' => 1, 'Name' => $this->Translate('manual'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('BuderusKM200.Hc_OperationMode', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations, $reInstall);

        $associations = [
            ['Wert' => 0, 'Name' => $this->Translate('Error'), 'Farbe' => 0xEE0000],
            ['Wert' => 1, 'Name' => $this->Translate('Maintenance'), 'Farbe' => 0xFFFF00],
            ['Wert' => 2, 'Name' => $this->Translate('Ok'), 'Farbe' => 0x228B22],
        ];
        $this->CreateVarProfile('BuderusKM200.HealthStatus', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations, $reInstall);

        $this->CreateVarProfile('BuderusKM200.min', VARIABLETYPE_INTEGER, ' min', 0, 0, 0, 0, '', [], $reInstall);

        $associations = [
            ['Wert' => 0, 'Name' => $this->Translate('Inactive'), 'Farbe' => -1],
            ['Wert' => 1, 'Name' => $this->Translate('Active'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('BuderusKM200.Status', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations, $reInstall);

        $this->CreateVarProfile('BuderusKM200.bar', VARIABLETYPE_FLOAT, ' bar', 0, 0, 0, 0, '', [], $reInstall);

        $associations = [
            ['Wert' => -3276.8, 'Name' => '-', 'Farbe' => -1],
            ['Wert' => -3276.7, 'Name' => '%.1f °C', 'Farbe' => -1],
        ];
        $this->CreateVarProfile('BuderusKM200.Celsius', VARIABLETYPE_FLOAT, '', 0, 0, 0, 1, 'Temperature', $associations, $reInstall);

        $this->CreateVarProfile('BuderusKM200.kWh', VARIABLETYPE_FLOAT, ' kWh', 0, 0, 0, 0, '', [], $reInstall);
        $this->CreateVarProfile('BuderusKM200.kW', VARIABLETYPE_FLOAT, ' kW', 0, 0, 0, 1, '', [], $reInstall);
        $this->CreateVarProfile('BuderusKM200.l_min', VARIABLETYPE_FLOAT, ' l/min', 0, 0, 0, 0, '', [], $reInstall);
        $this->CreateVarProfile('BuderusKM200.Pascal', VARIABLETYPE_FLOAT, ' Pascal', 0, 0, 0, 0, '', [], $reInstall);
        $this->CreateVarProfile('BuderusKM200.Percent', VARIABLETYPE_FLOAT, ' %', 0, 0, 0, 0, '', [], $reInstall);
        $this->CreateVarProfile('BuderusKM200.Wh', VARIABLETYPE_FLOAT, ' Wh', 0, 0, 0, 0, '', [], $reInstall);
    }
}
