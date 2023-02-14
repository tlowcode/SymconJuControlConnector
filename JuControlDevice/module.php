<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/WebClient.php';
require_once __DIR__ . '/../libs/DebugHelper.php';

	class JuControlDevice extends IPSModule
	{

        //Status
        private const STATUS_INST_AUTHENTICATION_FAILED = 201;
        private const STATUS_INST_WRONG_DEVICETYPE  = 202;
        private const STATUS_INST_DEVICE_NOT_ONLINE = 203;
        private const STATUS_INST_DEVICE_NOT_FOUND = 204;
        private const STATUS_INST_INFORMATION_INCOMPLETE = 205;

        //Properties
        private const PROP_USERNAME = 'Username';
        private const PROP_PASSWORD = 'Password';
        private const PROP_DEVICETYPE = 'DeviceType';
        private const PROP_SERIALNUMBER = 'SerialNumber';
        private const PROP_REFRESHRATE = 'RefreshRate';

        //attributes
        private const ATTR_TOKEN_KNM  = 'AccessTokenMyJudoEU';
        private const ATTR_TOKEN_JUDO = 'AccessTokenMyJudoCom';
        private const ATTR_DEVICEDATA = 'DeviceData';

        //variable idents
        private const VAR_IDENT_DEVICESTATE = 'deviceState';
        private const VAR_IDENT_BATTERYSTATE = 'batteryState';
        private const VAR_IDENT_BATTERYRUNTIME = 'batteryRuntime';
        private const VAR_IDENT_CURRENTFLOW = 'currentFlow';
        private const VAR_IDENT_WATERSTOP = 'waterStop';
        private const VAR_IDENT_SLEEPMODE                = 'sleepMode';
        private const VAR_IDENT_HOLIDAY                  = 'holiday';
        private const VAR_IDENT_WATERSTOP_MAXPERIODOFUSE = 'wsMaxPeriodOfUse';
        private const VAR_IDENT_WATERSTOP_MAXQUANTITY = 'wsMaxQuantity';
        private const VAR_IDENT_WATERSTOP_MAXWATERFLOW = 'wsMaxWaterFlow';
        private const VAR_IDENT_WATERSTOP_HOLIDAYMODE = 'wsHolidayMode';
        private const VAR_IDENT_WATERSTOP_SLEEPMODEDURATION = 'wsSleepModeDuration';
        private const VAR_IDENT_ACTIVESCENE = 'activeScene';
        private const VAR_IDENT_RANGESALTPERCENT = 'rangeSaltPercent';
        private const VAR_IDENT_RANGESALTDAYS = 'rangeSaltDays';
        private const VAR_IDENT_SALTLEVEL = 'saltLevel';
        private const VAR_IDENT_HARDNESS_WASHING = 'Hardness_Washing';
        private const VAR_IDENT_HARDNESS_SHOWER = 'Hardness_Shower';
        private const VAR_IDENT_HARDNESS_HEATER = 'Hardness_Heater';
        private const VAR_IDENT_HARDNESS_WATERING = 'Hardness_Watering';
        private const VAR_IDENT_HARDNESS_NORMAL = 'Hardness_Normal';
        private const VAR_IDENT_TIME_WASHING    = 'Time_Washing';
        private const VAR_IDENT_TIME_SHOWER       = 'Time_Shower';
        private const VAR_IDENT_TIME_HEATER   = 'Time_Heater';
        private const VAR_IDENT_TIME_WATERING = 'Time_Watering';
        private const VAR_IDENT_REGENERATION = 'Regeneration';
        private const VAR_IDENT_INPUT_HARDNESS = 'inputHardness';
        private const VAR_IDENT_TARGET_HARDNESS = 'targetHardness';
        private const VAR_IDENT_SWVERSION = 'swVersion';
        private const VAR_IDENT_HWVERSION = 'hwVersion';
        private const VAR_IDENT_TOTAL_REGENERATION = 'totalRegeneration';
        private const VAR_IDENT_TOTAL_WATER = 'totalWater';
        private const VAR_IDENT_INSTALLATION_DATE = 'InstallationDate';
        private const VAR_IDENT_NEXT_SERVICE = 'nextService';
        private const VAR_IDENT_NEXT_SERVICE_DATE = 'nextServiceDate';

        private const SERVER_KNM  = 'https://www.myjudo.eu:443/interface';
        private const SERVER_JUDO = 'https://www.my-judo.com:8124';

        private const DT_I_SOFT_SAFE_PLUS = '0x33';
        private const DT_I_SOFT_PLUS = 'i-soft plus';

		public function Create()
		{
			//Never delete this line!
			parent::Create();

            //attributes
            $this->RegisterAttributeString(self::ATTR_TOKEN_KNM, "noToken");
            $this->RegisterAttributeString(self::ATTR_TOKEN_JUDO, "noToken");
            $this->RegisterAttributeString(self::ATTR_DEVICEDATA, '');

			//timer
            $this->RegisterTimer("RefreshTimer", 0, 'JCD_RefreshData('. $this->InstanceID . ');');
            $this->RegisterTimer("SleepTimer", 0, 'JCD_Wakeup('. $this->InstanceID . ');');

			//properties
            $this->RegisterPropertyString(self::PROP_USERNAME, "");
			$this->RegisterPropertyString(self::PROP_PASSWORD, "");
			$this->RegisterPropertyString(self::PROP_DEVICETYPE, "");
			$this->RegisterPropertyString(self::PROP_SERIALNUMBER, "");
			$this->RegisterPropertyInteger(self::PROP_REFRESHRATE, 60);


			$this->SetStatus(IS_INACTIVE);

		}

        private function RegisterVariables(string $deviceType)
        {
            $position = -1;

            //common profiles
            $this->RegisterProfileInteger("JCD.lph", "Drops", "", " l/h", 0, 0, 0);
            $this->RegisterProfileInteger("JCD.dH_int", "Drops", "", " °dH", 0, 50, 1);
            $this->RegisterProfileFloat("JCD.dH_float", "Drops", "", " °dH", 0, 50, 0.1);
            $this->RegisterProfileInteger("JCD.Days", "Clock", "", $this->Translate(' days'), 0, 0, 0);
            $this->RegisterProfileInteger('JCD.kg', '', '', ' kg', 0, 0, 0);
            $this->RegisterProfileInteger("JCD.Liter", "Wave", "", $this->Translate(' liters'), 0, 99999999, 1);
            $this->RegisterProfileInteger('JCD.Minutes.WSMaxPeriodOfUse', 'Clock', '', $this->Translate(' minutes'), 0, 600, 10);
            $this->RegisterProfileInteger('JCD.Waterscene', "Drops", "", "", 0, 4, 0);
            $this->RegisterProfileInteger('JCD.Liters.WSMaxQuantity', '', '', " l", 0, 3000, 100);
            $this->RegisterProfileInteger('JCD.lph.WSMaxWaterFlow', '', '', " l/h", 0, 5000, 100);
            IPS_SetVariableProfileAssociation('JCD.Waterscene', 0, $this->Translate('Normal mode'), 'Ok', 0x00FF00);
            IPS_SetVariableProfileAssociation('JCD.Waterscene', 1, $this->Translate('Shower'), 'Shower', 0xFF9C00);
            IPS_SetVariableProfileAssociation('JCD.Waterscene', 2, $this->Translate('Filling of heating'), 'Temperature', 0xFF9C00);
            IPS_SetVariableProfileAssociation('JCD.Waterscene', 3, $this->Translate('Garden irrigation'), 'Drops', 0xFF9C00);
            IPS_SetVariableProfileAssociation('JCD.Waterscene', 4, $this->Translate('Washing'), 'Pants', 0xFF9C00);

            //common variables
            $this->RegisterVariableString(self::VAR_IDENT_DEVICESTATE, $this->Translate('State'), "", ++$position);
            $this->RegisterVariableFloat(self::VAR_IDENT_INPUT_HARDNESS, $this->Translate('Input water hardness'), "JCD.dH_float", ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_TARGET_HARDNESS, $this->Translate('Desired water hardness'), "JCD.dH_int", ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_SALTLEVEL, $this->Translate('Salt storage'), 'JCD.kg', ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_RANGESALTPERCENT, $this->Translate('Fill level salt'), '~Intensity.100', ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_RANGESALTDAYS, $this->Translate('Range salt storage'), 'JCD.Days', ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_CURRENTFLOW, $this->Translate('Water flow'), 'JCD.lph', ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_WATERSTOP_MAXPERIODOFUSE, $this->Translate('Max. Period of Use'), 'JCD.Minutes.WSMaxPeriodOfUse', ++$position);

            $this->RegisterVariableInteger(self::VAR_IDENT_WATERSTOP_MAXPERIODOFUSE, $this->Translate('Max. Period of Use'), 'JCD.Minutes.WSMaxPeriodOfUse', ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_WATERSTOP_MAXQUANTITY, $this->Translate('Max. Quantity'), 'JCD.Liters.WSMaxQuantity', ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_WATERSTOP_MAXWATERFLOW, $this->Translate('Max. Water Flow'), 'JCD.lph.WSMaxWaterFlow', ++$position);
            $this->RegisterVariableBoolean(self::VAR_IDENT_WATERSTOP, $this->Translate('Water stop'), '~Switch', ++$position);
            $this->RegisterVariableBoolean(self::VAR_IDENT_REGENERATION, $this->Translate('Regeneration'), '~Switch', ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_HARDNESS_NORMAL, $this->Translate('Water scene hardness \'Normal\''), 'JCD.dH_int', ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_HARDNESS_WASHING, $this->Translate('Water scene hardness \'Washing\''), 'JCD.dH_int', ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_HARDNESS_HEATER, $this->Translate('Water scene hardness \'Filling of heating\''), 'JCD.dH_int', ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_HARDNESS_WATERING, $this->Translate('Water scene hardness \'Garden irrigation\''), 'JCD.dH_int', ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_HARDNESS_SHOWER, $this->Translate('Water scene hardness \'Shower\''), 'JCD.dH_int', ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_TIME_WASHING, $this->Translate('Water scene time \'Washing\''), 'JCD.Hours', ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_TIME_HEATER, $this->Translate('Water scene time \'Filling of heating\''), 'JCD.Hours', ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_TIME_WATERING, $this->Translate('Water scene time \'Garden irrigation\''), 'JCD.Hours', ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_TIME_SHOWER, $this->Translate('Water scene time \'Shower\''), 'JCD.Hours', ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_INSTALLATION_DATE, $this->Translate('Installation Date'), "~UnixTimestampDate", ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_NEXT_SERVICE_DATE, $this->Translate('Next Service Date'), "~UnixTimestampDate", ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_TOTAL_WATER, $this->Translate('Total water quantity'), "JCD.Liter", ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_TOTAL_REGENERATION, $this->Translate('Total regeneration rate'), "", ++$position);
            $this->RegisterVariableString("deviceType", $this->Translate('Device type'), "", ++$position);
            $this->RegisterVariableString("deviceSN", $this->Translate('Serial Number'), "", ++$position);
            $this->RegisterVariableString(self::VAR_IDENT_SWVERSION, $this->Translate('Software version'), "", ++$position);
            $this->RegisterVariableString(self::VAR_IDENT_HWVERSION, $this->Translate('Hardware version'), "", ++$position);
            $this->RegisterVariableInteger(self::VAR_IDENT_ACTIVESCENE, $this->Translate('Active water scene'), "JCD.Waterscene", ++$position);

            $this->EnableAction(self::VAR_IDENT_WATERSTOP);
            $this->EnableAction(self::VAR_IDENT_WATERSTOP_MAXWATERFLOW);
            $this->EnableAction(self::VAR_IDENT_WATERSTOP_MAXQUANTITY);
            $this->EnableAction(self::VAR_IDENT_WATERSTOP_MAXPERIODOFUSE);
            $this->EnableAction(self::VAR_IDENT_HARDNESS_NORMAL);
            $this->EnableAction(self::VAR_IDENT_HARDNESS_WASHING);
            $this->EnableAction(self::VAR_IDENT_HARDNESS_HEATER);
            $this->EnableAction(self::VAR_IDENT_HARDNESS_WATERING);
            $this->EnableAction(self::VAR_IDENT_HARDNESS_SHOWER);
            $this->EnableAction(self::VAR_IDENT_TIME_WASHING);
            $this->EnableAction(self::VAR_IDENT_TIME_HEATER);
            $this->EnableAction(self::VAR_IDENT_TIME_WATERING);
            $this->EnableAction(self::VAR_IDENT_TIME_SHOWER);
            //$this->EnableAction(self::VAR_IDENT_ACTIVESCENE); funktioniert scheinbar über das Webfront nicht richtig.z.B. wird bei Garten immer gleich wieder auf normal zurückgeschaltet


            if ($deviceType === self::DT_I_SOFT_SAFE_PLUS) {
                //i-soft safe profiles
                $this->RegisterProfileInteger("JCD.Minutes", "Clock", "", $this->Translate(' minutes'), 0, 0, 0);
                $this->RegisterProfileInteger("JCD.Hours", "Clock", "", $this->Translate(' hours'), 0, 10, 1);
                $this->RegisterProfileInteger('JCD.WSHolidayMode', '', '', '', 0, 3, 0);
                IPS_SetVariableProfileAssociation('JCD.WSHolidayMode', 0, $this->Translate('no holiday mode'), '', -1);
                IPS_SetVariableProfileAssociation('JCD.WSHolidayMode', 1, $this->Translate('Holiday mode 1'), '', -1);
                IPS_SetVariableProfileAssociation('JCD.WSHolidayMode', 2, $this->Translate('Holiday mode 2'), '', -1);
                IPS_SetVariableProfileAssociation('JCD.WSHolidayMode', 3, $this->Translate('Shut off water'), '', -1);
                $this->RegisterProfileBoolean('JCD.NoYes', '', '', '', [
                    [false, $this->Translate('No'), '', -1],
                    [true, $this->Translate('Yes'), '', -1]
                ]);

                //i-soft safe variables
                $this->RegisterVariableString("deviceID", $this->Translate('Device number'), "", ++$position);

                $this->RegisterVariableInteger(self::VAR_IDENT_WATERSTOP_HOLIDAYMODE, $this->Translate('Holiday mode'), 'JCD.WSHolidayMode', ++$position);
                $this->EnableAction(self::VAR_IDENT_WATERSTOP_HOLIDAYMODE);

                $this->RegisterVariableBoolean(self::VAR_IDENT_HOLIDAY, $this->Translate('Holiday'), '~Switch', ++$position);
                $this->EnableAction(self::VAR_IDENT_HOLIDAY);

                $this->RegisterVariableBoolean(self::VAR_IDENT_SLEEPMODE, $this->Translate('Sleep mode'), '~Switch', ++$position);
                $this->EnableAction(self::VAR_IDENT_SLEEPMODE);

                $this->RegisterVariableInteger(self::VAR_IDENT_WATERSTOP_SLEEPMODEDURATION, $this->Translate('Sleep mode duration'), 'JCD.Hours', ++$position);
                $this->EnableAction(self::VAR_IDENT_WATERSTOP_SLEEPMODEDURATION);

                $this->RegisterVariableInteger(self::VAR_IDENT_BATTERYSTATE, $this->Translate('Battery status'), '~Intensity.100', ++$position);
                $this->RegisterVariableString(self::VAR_IDENT_BATTERYRUNTIME, $this->Translate('Batterielaufzeit (H:MM:SS)'), '', ++$position);

                $this->RegisterVariableString("ccuVersion", $this->Translate('Connectivity module version'), "", ++$position);
                $this->RegisterVariableInteger(self::VAR_IDENT_NEXT_SERVICE, $this->Translate('Next service'), "JCD.Days", ++$position);
                $this->RegisterVariableBoolean("hasEmergencySupply", $this->Translate('Safety-Modul'), "JCD.NoYes", ++$position);

                $this->EnableAction(self::VAR_IDENT_REGENERATION);

                $this->RegisterVariableInteger("totalService", $this->Translate('Number of services'), "", ++$position);

                $this->RegisterVariableInteger("remainingTime", $this->Translate('Remaining time scene'), "JCD.Minutes", ++$position);

                $this->EnableAction(self::VAR_IDENT_ACTIVESCENE); // the setting only works with i-soft SAFE+

            }
        }
		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();

            if (in_array('', [$this->ReadPropertyString(self::PROP_USERNAME),
                $this->ReadPropertyString(self::PROP_PASSWORD),
                $this->ReadPropertyString(self::PROP_DEVICETYPE)],
            true)){
                $this->SetStatus(self::STATUS_INST_INFORMATION_INCOMPLETE);
                return;
            }

            $this->RegisterVariables($this->ReadPropertyString(self::PROP_DEVICETYPE));

            if ($this->Login()){
                $this->RefreshData();
            }
		}

        private function RequestAction_Plus(string $Ident, $Value): void
        {
            $url = self::SERVER_JUDO;

            switch ($Ident) {
                case self::VAR_IDENT_WATERSTOP:
                    $response = $this->SendCommand(self::SERVER_JUDO, [
                        'group'     => 'waterstop',
                        'command'   => 'valve',
                        'parameter' => $Value ? 'close' : 'open'
                    ]);
                    break;

                case self::VAR_IDENT_ACTIVESCENE:
                    switch ($Value) {
                        case 0:
                            $parameter = 'normal';
                            break;
                        case 1:
                            $parameter = 'shower';
                            break;
                        case 2:
                            $parameter = 'heaterfilling';
                            break;
                        case 3:
                            $parameter = 'watering';
                            break;
                        case 4:
                            $parameter = 'washing';
                            break;
                        default:
                            trigger_error(sprintf('%s: invalid scene (%s)', __FUNCTION__, $Value), E_USER_ERROR);
                    }

                    $response = $this->SendCommand(self::SERVER_KNM, [
                        'group'        => 'register',
                        'command'      => 'set_optisoft_waterscene',
                        'serialnumber' => $this->GetValue('deviceSN'),
                        'parameter'    => $parameter,
                        'time'         => time() + 60 * 60
                    ]);
                    break;

                case self::VAR_IDENT_HARDNESS_NORMAL:
                    $response = $this->SendCommand(self::SERVER_JUDO, [
                        'group'     => 'settings',
                        'command'   => 'residual hardness',
                        'parameter' => $Value
                    ]);
                    if (!$this->isResponseOK($response)){
                        break;
                    }

                    $response = $this->SendCommand(self::SERVER_KNM, [
                        'group'        => 'register',
                        'command'   => 'set_optisoft_waterhardness',
                        'serialnumber' => $this->GetValue('deviceSN'),
                        'parameter' => $Value
                    ]);
                    break;

                case self::VAR_IDENT_HARDNESS_HEATER:
                    $response = $this->SendCommand(self::SERVER_KNM, [
                        'group'        => 'register',
                        'command'      => 'set waterscene heaterfilling',
                        'serialnumber' => $this->GetValue('deviceSN'),
                        'parameter'    => $Value
                    ]);
                    break;

                case self::VAR_IDENT_HARDNESS_SHOWER:
                    $response = $this->SendCommand(self::SERVER_KNM, [
                        'group'        => 'register',
                        'command'      => 'set waterscene shower',
                        'serialnumber' => $this->GetValue('deviceSN'),
                        'parameter'    => $Value
                    ]);
                    break;

                case self::VAR_IDENT_HARDNESS_WASHING:
                    $response = $this->SendCommand(self::SERVER_KNM, [
                        'group'        => 'register',
                        'command'      => 'set waterscene washing',
                        'serialnumber' => $this->GetValue('deviceSN'),
                        'parameter'    => $Value
                    ]);
                    break;

                case self::VAR_IDENT_HARDNESS_WATERING:
                    $response = $this->SendCommand(self::SERVER_KNM, [
                        'group'        => 'register',
                        'command'      => 'set waterscene watering',
                        'serialnumber' => $this->GetValue('deviceSN'),
                        'parameter'    => $Value
                    ]);
                    break;

                case self::VAR_IDENT_TIME_HEATER:
                    $response = $this->SendCommand(self::SERVER_KNM, [
                        'group'        => 'register',
                        'command'      => 'set_optisoft_waterscene_time_heater',
                        'serialnumber' => $this->GetValue('deviceSN'),
                        'parameter'    => $Value
                    ]);
                    break;

                case self::VAR_IDENT_TIME_SHOWER:
                    $response = $this->SendCommand(self::SERVER_KNM, [
                        'group'        => 'register',
                        'command'      => 'set_optisoft_waterscene_time',
                        'serialnumber' => $this->GetValue('deviceSN'),
                        'parameter'    => $Value
                    ]);
                    break;

                case self::VAR_IDENT_TIME_WASHING:
                    $response = $this->SendCommand(self::SERVER_KNM, [
                        'group'        => 'register',
                        'command'      => 'set_optisoft_waterscene_time_washing',
                        'serialnumber' => $this->GetValue('deviceSN'),
                        'parameter'    => $Value
                    ]);
                    break;

                case self::VAR_IDENT_TIME_WATERING:
                    $response = $this->SendCommand(self::SERVER_KNM, [
                        'group'        => 'register',
                        'command'      => 'set_optisoft_waterscene_time_garden',
                        'serialnumber' => $this->GetValue('deviceSN'),
                        'parameter'    => $Value
                    ]);
                    break;

                case self::VAR_IDENT_WATERSTOP_MAXPERIODOFUSE:
                    $response = $this->SendCommand(self::SERVER_JUDO, [
                        'group'     => 'waterstop',
                        'command'   => 'abstraction time',
                        'parameter' => $Value
                    ]);
                    break;

                case self::VAR_IDENT_WATERSTOP_MAXQUANTITY:
                    $response = $this->SendCommand(self::SERVER_JUDO, [
                        'group'     => 'waterstop',
                        'command'   => 'quantity',
                        'parameter' => $Value
                    ]);
                    break;

                case self::VAR_IDENT_WATERSTOP_MAXWATERFLOW:
                    $response = $this->SendCommand(self::SERVER_JUDO, [
                        'group'     => 'waterstop',
                        'command'   => 'flow rate',
                        'parameter' => $Value
                    ]);
                    break;

                default:
                    trigger_error('Unexpected Ident: ' . $Ident, E_USER_WARNING);
                    return;
            }

            if ($this->isResponseOK($response)) {
                $this->SetValue($Ident, $Value);
            }
        }

        private function isResponseOK(string $response): bool{
            $json = json_decode($response, true);
            return (isset($json['status']) && ($json['status'] === 'ok'));
        }
		/* wird aufgerufen, wenn eine Variable geändert wird */
		public function RequestAction($Ident, $Value) {

            $this->SendDebug(__FUNCTION__, sprintf('Ident: %s, Value: %s', $Ident, $Value), 0);

            switch ($this->ReadPropertyString(self::PROP_DEVICETYPE)) {
                case self::DT_I_SOFT_SAFE_PLUS:
                    break;

                case self::DT_I_SOFT_PLUS:
                    $this->RequestAction_Plus($Ident, $Value);
                    return;

                default:
            }

			$strSerialnumber = '&serialnumber=';

			switch ($Ident) {
                case self::VAR_IDENT_HARDNESS_WASHING:
					$command = "set%20waterscene%20washing";
					$parameter = (string) $Value;
					break;
                case self::VAR_IDENT_HARDNESS_HEATER:
					$command = "set%20waterscene%20heaterfilling";
					$parameter = (string) $Value;
					break;
				case self::VAR_IDENT_HARDNESS_WATERING:
					$command = "set%20waterscene%20watering";
					$parameter = (string) $Value;
					break;
				case self::VAR_IDENT_HARDNESS_SHOWER:
					$command = "set%20waterscene%20shower";
					$parameter = (string) $Value;
					break;
                case self::VAR_IDENT_TIME_WASHING:
                    $command = "set_waterscene_time_washing";
                    $parameter = (string) $Value;
                    $strSerialnumber = '&serial_number=';
                    break;
                case self::VAR_IDENT_TIME_HEATER:
                    $command = "set_waterscene_time_heater";
                    $parameter = (string) $Value;
                    $strSerialnumber = '&serial_number=';
                    break;
                case self::VAR_IDENT_TIME_WATERING:
                    $command = "set_waterscene_time_garden";
                    $parameter = (string) $Value;
                    $strSerialnumber = '&serial_number=';
                    break;
                case self::VAR_IDENT_TIME_SHOWER:
                    $command = "set_waterscene_time";
                    $parameter = (string) $Value;
                    $strSerialnumber = '&serial_number=';
                    break;
                case self::VAR_IDENT_HARDNESS_NORMAL:
					$command = "write%20data&dt=0x33&index=60&data=". $Value . "&da=0x1&&action=normal";
					break;
                case self::VAR_IDENT_WATERSTOP_MAXPERIODOFUSE:
					$command = "write%20data&dt=0x33&index=74&data=". substr($this->formatEndian($Value), 0, 4) . "&da=0x1";
                    $strSerialnumber = '&serial_number=';
					break;
                case self::VAR_IDENT_WATERSTOP_MAXQUANTITY:
					$command = "write%20data&dt=0x33&index=76&data=". substr($this->formatEndian($Value), 0, 4) . "&da=0x1";
                    $strSerialnumber = '&serial_number=';
					break;
                case self::VAR_IDENT_WATERSTOP_MAXWATERFLOW:
					$command = "write%20data&dt=0x33&index=75&data=". substr($this->formatEndian($Value), 0, 4) . "&da=0x1";
                    $strSerialnumber = '&serial_number=';
					break;
                case self::VAR_IDENT_WATERSTOP_HOLIDAYMODE:
                    $deviceData = json_decode($this->ReadAttributeString(self::ATTR_DEVICEDATA), true);
                    $wsUrlaub = str_pad(decbin($this->getInValue($deviceData, 792, 18)), 8, '0', STR_PAD_LEFT);
                    switch ($Value) {
                        case 1:
                            $wsUrlaub[6] = '1';
                            $wsUrlaub[5] = '0';
                            $wsUrlaub[4] = '0';
                            break;
                        case 2:
                            $wsUrlaub[6] = '0';
                            $wsUrlaub[5] = '1';
                            $wsUrlaub[4] = '0';
                            break;
                        case 3:
                            $wsUrlaub[6] = '0';
                            $wsUrlaub[5] = '0';
                            $wsUrlaub[4] = '1';
                            break;
                        default:
                            $wsUrlaub[6] = '0';
                            $wsUrlaub[5] = '0';
                            $wsUrlaub[4] = '0';
                    }

                    $command = "write%20data&dt=0x33&index=77&data=". str_pad(dechex(bindec($wsUrlaub)), 2, '0', STR_PAD_LEFT) . "&da=0x1";
                    $strSerialnumber = '&serial_number=';
					break;

                case self::VAR_IDENT_WATERSTOP_SLEEPMODEDURATION:
                    $command = "write%20data&dt=0x33&index=171&data=". $Value . "&da=0x1";
                    $strSerialnumber = '&serial_number=';
                    break;

                case self::VAR_IDENT_WATERSTOP:
                    if ($Value){
                        $command = "write%20data&dt=0x33&index=72&data=&da=0x1";
                    } else {
                        $command = "write%20data&dt=0x33&index=73&data=&da=0x1";
                    }
                    $strSerialnumber = '&serial_number=';
                    break;

                case self::VAR_IDENT_REGENERATION:
                    //die Regeneration lässt sich nur einschalten, nicht ausschalten
                    if ($Value){
                        $command = "write%20data&dt=0x33&index=65&data=&da=0x1";
                    }
                    $strSerialnumber = '&serial_number=';
                    break;

                case self::VAR_IDENT_SLEEPMODE:
                    if ($Value){
                       $command = "write%20data&dt=0x33&index=171&data=&da=0x1";
                   } else {
                       $command = "write%20data&dt=0x33&index=73&data=&da=0x1";
                   }
                   $strSerialnumber = '&serial_number=';
                   break;

                case self::VAR_IDENT_HOLIDAY:
                    if ($Value){
                       $command = "write%20data&dt=0x33&index=77&data=&da=0x1";
                   } else {
                       $command = "write%20data&dt=0x33&index=73&data=&da=0x1";
                   }
                   $strSerialnumber = '&serial_number=';
                   break;

				case self::VAR_IDENT_ACTIVESCENE:
					switch ($Value) {
						case 0:
							$action = "normal";
							$hardness = $this->GetValue(self::VAR_IDENT_HARDNESS_NORMAL);
							$command = "write%20data&dt=0x33&index=201&data=" . $hardness . "&da=0x1&disable_time=" . "&action=" . $action;
							break;
						case 1:
							$action = "shower";
							$time = $this->GetValue(self::VAR_IDENT_TIME_SHOWER);
							$hardness = $this->GetValue(self::VAR_IDENT_HARDNESS_SHOWER);
							$command = "write%20data&dt=0x33&index=202&data=" . $hardness . "&da=0x1&disable_time=". (time() + $time*60*60) . "&action=" . $action;
							break;
						case 2:
							$action = "heaterfilling";
							$time = $this->GetValue(self::VAR_IDENT_TIME_HEATER);
                            $hardness = $this->GetValue(self::VAR_IDENT_HARDNESS_HEATER);
							$command = "write%20data&dt=0x33&index=204&data=" . $hardness . "&da=0x1&disable_time=". (time() + $time*60*60) . "&action=" . $action;
							break;
						case 3:
							$action = "watering";
							$time = $this->GetValue(self::VAR_IDENT_TIME_WATERING);
                            $hardness = $this->GetValue(self::VAR_IDENT_HARDNESS_WATERING);
							$command = "write%20data&dt=0x33&index=203&data=" . $hardness . "&da=0x1&disable_time=". (time() + $time*60*60) . "&action=" . $action;
							break;
						case 4:
							$action = "washing";
							$time = $this->GetValue(self::VAR_IDENT_TIME_WASHING);
                            $hardness =  $this->GetValue(self::VAR_IDENT_HARDNESS_WASHING);
							$command = "write%20data&dt=0x33&index=205&data=" . $hardness . "&da=0x1&disable_time=". (time() + $time*60*60) . "&action=" . $action;
							break;
						default:
                            trigger_error(sprintf('%s: invalid scene (%s)', __FUNCTION__, $Value), E_USER_ERROR);
					}
					$strSerialnumber = '&serial_number=';
					$parameter = 0;
					break;

				default:
                    trigger_error(sprintf('%s: invalid ident (%s)', __FUNCTION__, $Ident), E_USER_ERROR);
			}

			if(isset($command)){
				$deviceCommandUrl = self::SERVER_KNM . '/?token=' . $this->ReadAttributeString(self::ATTR_TOKEN_KNM)
				. $strSerialnumber . $this->GetValue('deviceSN')
				. '&group=register&command='
				. $command;
                if (isset($parameter)){
                    $deviceCommandUrl .= '&parameter=' . $parameter;
                }

				$this->SendDebug(__FUNCTION__, 'Requesting API URL '. $deviceCommandUrl, 0);
                $wc = new WebClient();
				$response = $wc->Navigate($deviceCommandUrl);

                $json = json_decode($response, false);
				$this->SendDebug(__FUNCTION__, 'Received response from API: '. $response, 0);

				if($this->isResponseOK($response)) {
					$this->SetValue($Ident, $Value);
				} else {
					$this->SendDebug(__FUNCTION__, 'Error during request to JuControl API', 0);
                    $this->LogMessage('Error during request to JuControl API', KL_ERROR);
				}
			} else {
                trigger_error(__FUNCTION__ .': no command set', E_USER_ERROR);
            }

            $this->Sleep(10000);

		}

		public function SendCommand (string $url, array $data)
        {
            $wc = new WebClient();
            if (!isset($data['token'])){
                if ($url === self::SERVER_KNM){
                    $data['token'] = $this->ReadAttributeString(self::ATTR_TOKEN_KNM);
                } else {
                    $data['token'] = $this->ReadAttributeString(self::ATTR_TOKEN_JUDO);
                }
            }

            $dataLog = $data;
            if (isset($dataLog['password'])){
                $dataLog['password'] = '***';
            }
            if (isset($dataLog['nohash'])){
                $dataLog['nohash'] = '***';
            }

            $deviceDataUrl = $url . '/?' . http_build_query($data);
            $deviceDataUrlLog = $url . '/?' . http_build_query($dataLog);
            $this->SendDebug(sprintf('%s: %s', __FUNCTION__, $data['command']), 'url: '. $deviceDataUrlLog, 0);

            $response = $wc->Navigate($deviceDataUrl);

            if ($response === FALSE) {
                $this->SendDebug(__FUNCTION__, 'ERROR url: '. $deviceDataUrlLog, 0);
                $this->LogMessage('Error during request to JuControl API: '. $deviceDataUrlLog, KL_ERROR);
                return false;
            }
            $this->SendDebug(sprintf('%s: %s', __FUNCTION__, $data['command']), 'response: '. $response, 0);

            return $response;
        }

        private function dezimal2x8bitTo16bitDez(int $dez0, int $dez1): int
        {
            return bindec(decbin($dez0).decbin($dez1));
        }
        private function RefreshData_iSoftPlus(array $device): void
        {
            /* Device S/N */
            $this->updateIfNecessary($device['serial number'], "deviceSN");

            /* read input hardness */
            $this->updateIfNecessary((int) $device['data']['Tableread 0'][10], self::VAR_IDENT_INPUT_HARDNESS);

            /* read target hardness */
            $this->updateIfNecessary((int) $device['data']['residual hardness'], self::VAR_IDENT_TARGET_HARDNESS);

            /* SW Version */
            $this->updateIfNecessary($device['data']['software version'], self::VAR_IDENT_SWVERSION);

            /* HW Version */
            $this->updateIfNecessary($device['data']['hardware version'], self::VAR_IDENT_HWVERSION);

            /* Regeneration active*/
            $this->updateIfNecessary((int) $device['data']['Tableread 1'][0] === 1,self::VAR_IDENT_REGENERATION);

            /* Count regeneration */
            $this->updateIfNecessary($this->dezimal2x8bitTo16bitDez($device['data']['Tableread 1'][31], $device['data']['Tableread 1'][30]),self::VAR_IDENT_TOTAL_REGENERATION);

            /* currentFlow */
            $this->updateIfNecessary($this->dezimal2x8bitTo16bitDez($device['data']['Tableread 2'][11], $device['data']['Tableread 2'][10]),self::VAR_IDENT_CURRENTFLOW);

            /* Total water*/
            $this->updateIfNecessary((int) explode(' ', trim($device['data']['water total']))[0], self::VAR_IDENT_TOTAL_WATER);

            /* Salt Info*/
            $saltData = $device['data']['GET_SALT_Volume'];
            if (@strpos($saltData, ' ')){ // warning if needle is empty
                $saltInfo = explode(' ', $saltData);
                $SaltLevel = $saltInfo[0] / 1000; //Salzgewicht in kg
                $SaltLevelPercent = (int) (2 * $SaltLevel);
                $this->updateIfNecessary($SaltLevelPercent, self::VAR_IDENT_RANGESALTPERCENT);
                $this->updateIfNecessary(round($SaltLevel), self::VAR_IDENT_SALTLEVEL);
                $SaltRange = $saltInfo[1]; //Salzreichweite in Tagen
                $this->updateIfNecessary($SaltRange, self::VAR_IDENT_RANGESALTDAYS);
            }

            //Max Entnahmezeit
            $this->updateIfNecessary($this->dezimal2x8bitTo16bitDez($device['data']['Tableread 2'][17], $device['data']['Tableread 2'][16]), self::VAR_IDENT_WATERSTOP_MAXPERIODOFUSE);

            //Max Menge
            $this->updateIfNecessary($this->dezimal2x8bitTo16bitDez($device['data']['Tableread 2'][15], $device['data']['Tableread 2'][14]), self::VAR_IDENT_WATERSTOP_MAXQUANTITY);

            //Max Durchfluss
            $this->updateIfNecessary($this->dezimal2x8bitTo16bitDez($device['data']['Tableread 2'][13], $device['data']['Tableread 2'][12]), self::VAR_IDENT_WATERSTOP_MAXWATERFLOW);

            /* water stop */
            $this->updateIfNecessary((bool) $device['data']['Tableread 2'][0], self::VAR_IDENT_WATERSTOP);

            /* installation date */
            $this->updateIfNecessary((int) $device['data']['init date'], self::VAR_IDENT_INSTALLATION_DATE);

            /* service date */
            if ($device['data']['service date'] === '0'){
                $serviceResponse = json_decode($this->SendCommand(self::SERVER_JUDO, ['group'   => 'contract',
                                                                                      'command' => 'service date']), true);
                if (isset($serviceResponse['data'])){
                    $lastService = (int) $serviceResponse['data'];
                    if ($lastService === 0){
                        $nextServiceDate = strtotime('+ 1 year', (int) $device['data']['init date']) ;
                    } else {
                        $nextServiceDate = strtotime('+ 1 year', $lastService);
                    }
                    $this->updateIfNecessary($nextServiceDate, self::VAR_IDENT_NEXT_SERVICE_DATE);
                }
            } else {
                trigger_error(sprintf('Unexpected \'%s\': %s', 'service date', $device['data']['service date']), E_USER_WARNING);
            }


            //$this->SendDebug(__FUNCTION__, sprintf('next service: %s ', $nextService), 0);

        }
        private function RefreshData_iSoftSafe(array $device): void
        {
            /* Device S/N */
            $this->updateIfNecessary($device['serialnumber'], "deviceSN");

            /* installation date */
            $this->updateIfNecessary(strtotime($device['installation_date']), self::VAR_IDENT_INSTALLATION_DATE);

            /* Connectivity module version */
            $this->updateIfNecessary($device['data'][0]['sv'], "ccuVersion");


            $deviceData = $device['data'][0]['data'];
            $this->WriteAttributeString(self::ATTR_DEVICEDATA, json_encode($deviceData));

            /* Emergency supply available */
            $emergencyModuleData = $this->getInValue($deviceData, 790, 2);
            if (strlen($emergencyModuleData) > 1) {
                $emergencySupplyAvailable = (boolean) $emergencyModuleData[strlen($emergencyModuleData) - 2];
            } else {
                $emergencySupplyAvailable = false;
            }

            if ($emergencySupplyAvailable) {
                $this->updateIfNecessary(true, "hasEmergencySupply");

                $batteryValues = explode(':', $this->getInValue($deviceData, 93));

                /* Battery percentage */
                if (isset($batteryValues[0])){
                    $this->updateIfNecessary((int) $batteryValues[0], self::VAR_IDENT_BATTERYSTATE);
                }

                /* Battery runtime */
                if (count($batteryValues) > 1){
                    $batteryRuntime = sprintf('%d:%02d:%02d', (int) $batteryValues[3], (int) $batteryValues[2],  (int) $batteryValues[1]);
                    $this->updateIfNecessary($batteryRuntime, self::VAR_IDENT_BATTERYRUNTIME);
                }

            }
            else{
                $this->updateIfNecessary(false, "hasEmergencySupply");
            }

            /* Active scene */
            switch ($device['waterscene']) {
                case 'shower':
                    $sceneValue = 1;
                    break;
                case 'heaterfilling':
                    $sceneValue = 2;
                    break;
                case 'watering':
                    $sceneValue = 3;
                    break;
                case 'washing':
                    $sceneValue = 4;
                    break;
                default:
                    $sceneValue = 0;
                    break;
            }
            $this->updateIfNecessary($sceneValue, self::VAR_IDENT_ACTIVESCENE);


            /* SW Version */
            $this->updateIfNecessary($this->getInValue($deviceData, 1), self::VAR_IDENT_SWVERSION);

            /* HW Version */
            $this->updateIfNecessary($this->getInValue($deviceData, 2) , self::VAR_IDENT_HWVERSION);

            /* Device ID */
            $this->updateIfNecessary($this->getInValue($deviceData, 3), "deviceID");

            /* Service Info*/
            $infoService = explode (':', $this->getInValue($deviceData, 7));
            if (isset($infoService[0])){
                $nextService = (int) $infoService[0];
                $this->updateIfNecessary($nextService, self::VAR_IDENT_NEXT_SERVICE); //nächste Wartung in Tagen
                if ($nextService !== 0){
                    $this->updateIfNecessary(strtotime($nextService . ' days', time()), self::VAR_IDENT_NEXT_SERVICE_DATE);
                }
            }
            if (isset($infoService[1])) {
                $this->updateIfNecessary((int)$infoService[1], "totalService");
            }

            /* Total water*/
            $this->updateIfNecessary($this->getInValue($deviceData, 8), self::VAR_IDENT_TOTAL_WATER);

            /* Count regeneration */
            $totalRegeneration = $this->getInValue($deviceData, 791, 3031);
            if ($totalRegeneration !== ''){
                $this->updateIfNecessary($totalRegeneration, self::VAR_IDENT_TOTAL_REGENERATION);
            }

            /* Regeneration active*/
            $this->updateIfNecessary($this->getInValue($deviceData, 791, 0), self::VAR_IDENT_REGENERATION);

            /* Salt Info*/
            $saltData = $this->getInValue($deviceData, 94);
            if (@strpos($saltData, ':')){ // warning if needle is empty
                $saltInfo = explode(':', $saltData);
                $SaltLevel = $saltInfo[0] / 1000; //Salzgewicht in kg
                $SaltLevelPercent = (int) (2 * $SaltLevel);
                $this->updateIfNecessary($SaltLevelPercent, self::VAR_IDENT_RANGESALTPERCENT);
                $this->updateIfNecessary(round($SaltLevel), self::VAR_IDENT_SALTLEVEL);
                $SaltRange = $saltInfo[1]; //Salzreichweite in Tagen
                $this->updateIfNecessary($SaltRange, self::VAR_IDENT_RANGESALTDAYS);
            }

            /* Input hardness */
            $inputHardness = $this->getInValue($deviceData, 790, 26);
            $this->updateIfNecessary($inputHardness, self::VAR_IDENT_INPUT_HARDNESS);

            /* currentFlow */
            $currentFlow = $this->getInValue($deviceData, 790, 1617);
            $this->updateIfNecessary($currentFlow, self::VAR_IDENT_CURRENTFLOW);

            /* water stop */
            $leckageschutzStatusflag = $this->getInValue($deviceData, 792, 0);
            if (strlen($leckageschutzStatusflag) === 8) {
                $wasserstop = (boolean) $leckageschutzStatusflag[0];
            } else {
                $wasserstop = false;
            }
            $this->updateIfNecessary($wasserstop, self::VAR_IDENT_WATERSTOP);

            //Sleepmodus
            $standbyMode = $this->getInValue($deviceData, 792, 9);
            //$this->SendDebug(__FUNCTION__, 'standbymode: '. $standbyMode, KL_NOTIFY);

            $this->updateIfNecessary($standbyMode > 0, self::VAR_IDENT_SLEEPMODE);

            //Urlaub
            $wsUrlaub = str_pad(decbin($this->getInValue($deviceData, 792, 18)), 8, '0', STR_PAD_LEFT);

            $urlaubsmodusSelectValue = 0;
            switch ('1') {
                case $wsUrlaub[6]:
                    $urlaubsmodusSelectValue = 1;
                    break;
                case $wsUrlaub[5]:
                    $urlaubsmodusSelectValue = 2;
                    break;
                case $wsUrlaub[4]:
                    $urlaubsmodusSelectValue = 3;
                    break;
            }
            $this->updateIfNecessary($urlaubsmodusSelectValue, self::VAR_IDENT_WATERSTOP_HOLIDAYMODE);

            //Sleepmoduszeit
            $sleepmodusZeit = $this->getInValue($deviceData, 792, 19);
            if ($sleepmodusZeit < 1 || $sleepmodusZeit > 10){
                $sleepmodusZeit = 1;
            }
            $this->updateIfNecessary($sleepmodusZeit, self::VAR_IDENT_WATERSTOP_SLEEPMODEDURATION);

            //Max Durchfluss
            $maxDurchfluss = $this->getInValue($deviceData, 792, 1213);
            if ($maxDurchfluss > 5000) {
                $maxDurchfluss = 0;
            }
            $this->updateIfNecessary($maxDurchfluss, self::VAR_IDENT_WATERSTOP_MAXWATERFLOW);

            //Max Entnahmemenge
            $maxMenge = $this->getInValue($deviceData, 792, 1415);
            if ($maxMenge > 3000) {
                $maxMenge = 0;
            }
            $this->updateIfNecessary($maxMenge, self::VAR_IDENT_WATERSTOP_MAXQUANTITY);

            //Max Entnahmezeit
            $maxZeit = $this->getInValue($deviceData, 792, 1617);
            if ($maxZeit > 600) {
                $maxZeit = 0;
            }
            $this->updateIfNecessary($maxZeit, self::VAR_IDENT_WATERSTOP_MAXPERIODOFUSE);



            /* read target hardness */
            $this->updateIfNecessary($this->getInValue($deviceData, 790, 8), self::VAR_IDENT_TARGET_HARDNESS);


            /* Remaining time of active water scene */
            if($this->GetValue(self::VAR_IDENT_ACTIVESCENE) !== 0)
            {
                if($device['disable_time'] !== '')
                {
                    $remainingTime = (((int) $device['disable_time'] - time()) / 60) + 1;
                    $this->updateIfNecessary(max((int) $remainingTime, 0), "remainingTime");
                    /* update target hardness due to active waterscene */
                    switch ($this->GetValue(self::VAR_IDENT_ACTIVESCENE)) {
                        case '1':
                            $this->updateIfNecessary((int) $device['hardness_shower'], self::VAR_IDENT_TARGET_HARDNESS);
                            break;
                        case '2':
                            $this->updateIfNecessary((int) $device['hardness_heater'], self::VAR_IDENT_TARGET_HARDNESS);
                            break;
                        case '3':
                            $this->updateIfNecessary((int) $device['hardness_watering'], self::VAR_IDENT_TARGET_HARDNESS);
                            break;
                        case '4':
                            $this->updateIfNecessary((int) $device['hardness_washing'], self::VAR_IDENT_TARGET_HARDNESS);
                            break;

                        default:
                            # nothing to do
                            break;
                    }
                }
                else
                {
                    $this->updateIfNecessary(0, "remainingTime");
                    $this->updateIfNecessary(0, self::VAR_IDENT_ACTIVESCENE);
                }

            }
            else
            {
                $this->updateIfNecessary(0, "remainingTime");
            }


        }

        public function RefreshData(): bool
		{
			try {

                if ($this->GetValue(self::VAR_IDENT_DEVICESTATE) !== 'online'){
                    // if the device isn't online, first try to reconnect
                    if (!$this->Login()){
                        return false;
                    }
                }


                $response = $this->SendCommand(self::SERVER_KNM, ['group'   => 'register',
                                                                  'command' => 'get device data']);

				if ($response === FALSE) {
                    return false;
				}

                $json = json_decode($response, true);

                if (isset($json['status']) && ($json['status'] === 'ok'))
                {
                    $this->SendDebug(__FUNCTION__, 'get_device_data: ' . $response, 0);

                    /* Parse response */

                    $serialnumber = $this->ReadPropertyString(self::PROP_SERIALNUMBER);
                    if ($serialnumber !== ''){
                        $device = [];
                        foreach ($json['data'] as $data){
                            if ($data['serialnumber'] === $serialnumber){
                                $device = $data;
                                break;
                            }
                        }
                        if ($device === []){
                            $this->SetStatus(self::STATUS_INST_DEVICE_NOT_FOUND);
                            return false;
                        }
                    } else {
                        $device = $json['data'][0];
                    }

                    $this->updateIfNecessary($device['status'], self::VAR_IDENT_DEVICESTATE);

                    /* Device online */
                    if ($device['status'] !== 'online') {
                        $this->SetStatus(self::STATUS_INST_DEVICE_NOT_ONLINE);
                        return false;
                    }


                    /* read target hardness of waterscenes */
                    $this->updateIfNecessary((int) $device['hardness_washing'], self::VAR_IDENT_HARDNESS_WASHING);
                    $this->updateIfNecessary((int) $device['hardness_shower'], self::VAR_IDENT_HARDNESS_SHOWER);
                    $this->updateIfNecessary((int) $device['hardness_watering'], self::VAR_IDENT_HARDNESS_WATERING);
                    $this->updateIfNecessary((int) $device['hardness_heater'], self::VAR_IDENT_HARDNESS_HEATER);
                    $this->updateIfNecessary((int) $device['waterscene_normal'], self::VAR_IDENT_HARDNESS_NORMAL);

                    /* read times of waterscenes */
                    if (isset($device['waterscene_time'])){
                        $this->updateIfNecessary((int) $device['waterscene_time'], self::VAR_IDENT_TIME_SHOWER);
                    } else {
                        $this->updateIfNecessary(2, self::VAR_IDENT_TIME_SHOWER);
                    }
                    if (isset($device['waterscene_time_garden'])){
                        $this->updateIfNecessary((int) $device['waterscene_time_garden'], self::VAR_IDENT_TIME_WATERING);
                    } else {
                        $this->updateIfNecessary(2, self::VAR_IDENT_TIME_WATERING);
                    }
                    if (isset($device['waterscene_time_heater'])){
                        $this->updateIfNecessary((int) $device['waterscene_time_heater'], self::VAR_IDENT_TIME_HEATER);
                    } else {
                        $this->updateIfNecessary(2, self::VAR_IDENT_TIME_HEATER);
                    }
                    if (isset($device['waterscene_time_washing'])){
                        $this->updateIfNecessary((int) $device['waterscene_time_washing'], self::VAR_IDENT_TIME_WASHING);
                    } else {
                        $this->updateIfNecessary(2, self::VAR_IDENT_TIME_WASHING);
                    }

                    /* Device Type */
                    $deviceType = $this->ReadPropertyString(self::PROP_DEVICETYPE);

                    switch ($deviceType){
                        case self::DT_I_SOFT_SAFE_PLUS:
                            $dt = $device['data'][0]['dt'];
                            if ($dt === $deviceType){
                                $this->SetStatus(IS_ACTIVE);
                                $this->updateIfNecessary('i-soft safe', "deviceType");
                            } else {
                                $this->SetStatus(self::STATUS_INST_WRONG_DEVICETYPE);
                                $this->SendDebug(__FUNCTION__, 'Wrong device type (' . $dt . ') found -> Aborting!', 0);
                                $this->LogMessage('Wrong device type (' . $dt . ') found -> Aborting!', KL_ERROR);
                                $this->SetTimerInterval("RefreshTimer", 0);
                                return false;
                            }

                            $this->RefreshData_iSoftSafe($device);
                            break;

                        case self::DT_I_SOFT_PLUS:

                            $serialnumber = $device['serialnumber'];

                            $this->SendCommand(self::SERVER_JUDO, ['group'         => 'register',
                                                                   'parameter'     => self::DT_I_SOFT_PLUS,
                                                                   'serial number' => $serialnumber,
                                                                   'command'       => 'disconnect']);

                            $responseMyJudoCom_connect = $this->SendCommand(self::SERVER_JUDO, ['group'         => 'register',
                                                                   'parameter'     => self::DT_I_SOFT_PLUS,
                                                                   'serial number' => $serialnumber,
                                                                   'command'       => 'connect']);

                            if ($responseMyJudoCom_connect === false){
                                return false;
                            }

                            $connect = json_decode($responseMyJudoCom_connect, true);

                            if (!isset($connect['status']) || $connect['status'] !== 'ok') {
                                // if the device isn't online, first try to reconnect
                                if (!$this->Login()){
                                    return false;
                                }
                            }

                            $responseMyJudoCom_combinedData = $this->SendCommand(
                                self::SERVER_JUDO,
                                [
                                    'group'   => 'device',
                                    'command' => 'combined data'
                                ]
                            );

                            if ($responseMyJudoCom_combinedData === false){
                                return false;
                            }

                            $device = json_decode($responseMyJudoCom_combinedData, true);

                            if (!isset($device['status']) || $device['status'] !== 'ok') {
                                return false;
                            }

                            if ($device['wtuType'] === $deviceType){
                                $this->SetStatus(IS_ACTIVE);
                                $this->updateIfNecessary('i-soft plus', "deviceType");
                            } else {
                                $this->SetStatus(self::STATUS_INST_WRONG_DEVICETYPE);
                                $this->SendDebug(__FUNCTION__, 'Wrong device type (' . $device['wtuType']  . ') found -> Aborting!', 0);
                                $this->LogMessage('Wrong device type (' .$device['wtuType']  . ') found -> Aborting!', KL_ERROR);
                                $this->SetTimerInterval("RefreshTimer", 0);
                                return false;
                            }

                            $this->RefreshData_iSoftPlus($device);

                            $response_waterscene = $this->SendCommand(
                                self::SERVER_KNM,
                                [
                                    'group'        => 'register',
                                    'command'      => 'get_optisoft_waterscene',
                                    'serialnumber' => $serialnumber
                                ]
                            );

                            if ($response_waterscene === false) {
                                return false;
                            }

                            $data = json_decode($response_waterscene, true)['data'];

                            /* Active scene */
                            switch ($data['waterscene']) {
                                case 'shower':
                                    $sceneValue = 1;
                                    break;
                                case 'heaterfilling':
                                    $sceneValue = 2;
                                    break;
                                case 'watering':
                                    $sceneValue = 3;
                                    break;
                                case 'washing':
                                    $sceneValue = 4;
                                    break;
                                default:
                                    $sceneValue = 0;
                                    break;
                            }
                            $this->updateIfNecessary($sceneValue, self::VAR_IDENT_ACTIVESCENE);



                            break;
                    }

                } else {
                    /* Token not valid -> try to log in again one time and wait for next RefreshData! */
                    $this->Login();
                }

            }
			catch(Exception $e){
				$this->SendDebug(__FUNCTION__, 'Error during data crawling: '. $e->getMessage(), 0);
				$this->LogMessage('Error during data crawling: '. $e->getMessage(), KL_ERROR);
			}

			return true;

		}

		private function Login(): bool
        {

			$username = $this->ReadPropertyString(self::PROP_USERNAME);
			$passwd = $this->ReadPropertyString(self::PROP_PASSWORD);

            $responseMyJudoEU = $this->SendCommand(
                self::SERVER_KNM, [
                                    'group'    => 'register',
                                    'command'  => 'login',
                                    'name'     => 'login',
                                    'user'     => $username,
                                    'password' => md5($passwd),
                                    'nohash'   => $passwd,
                                    'role'     => 'customer'
                                ]
            );

            $responseMyJudoCom = $this->SendCommand(
                self::SERVER_JUDO, [
                                     'group'    => 'register',
                                     'command'  => 'login',
                                     'name'     => 'login',
                                     'user'     => $username,
                                     'password' => $passwd,
                                     'role'     => 'customer'
                                 ]
            );


            if (!$responseMyJudoEU || !$responseMyJudoCom )
			{
				$this->SetStatus(self::STATUS_INST_AUTHENTICATION_FAILED);
                return false;
			}

            $jsonMyJudoEU = json_decode($responseMyJudoEU, false);
            $jsonMyJudoCom = json_decode($responseMyJudoCom, false);
            if (isset($jsonMyJudoEU->status, $jsonMyJudoCom->status) && ($jsonMyJudoEU->status === 'ok') && ($jsonMyJudoCom->status === 'ok'))
            {
                $this->SendDebug(__FUNCTION__, sprintf('Login successful, Token %s: %s, Token %s: %s', self::SERVER_KNM, $jsonMyJudoEU->token, self::SERVER_JUDO, $jsonMyJudoCom->token), 0);
                $this->WriteAttributeString(self::ATTR_TOKEN_KNM, $jsonMyJudoEU->token);
                $this->WriteAttributeString(self::ATTR_TOKEN_JUDO, $jsonMyJudoCom->token);
                $this->SetStatus(IS_ACTIVE);

                $refreshRate = $this->ReadPropertyInteger("RefreshRate");
                $this->SetTimerInterval("RefreshTimer", $refreshRate * 1000);
            }
            else
            {
                $this->SendDebug(__FUNCTION__, 'Login failed!', 0);
                $this->LogMessage('Login failed!', KL_ERROR);
                $this->SetStatus(self::STATUS_INST_AUTHENTICATION_FAILED);
                $this->SetTimerInterval("RefreshTimer", 0);
                return false;
            }

            //check the serial number and sync the devices
            if ($respShow = $this->SendCommand(self::SERVER_JUDO, ['group' => 'register', 'command' => 'show', 'application' => 'JC'])){
                $data = json_decode($respShow, true)['data'];
                $serialnumber = $this->ReadPropertyString(self::PROP_SERIALNUMBER);
                if ($serialnumber && !in_array($serialnumber, array_column($data, 'serial number'), true)){
                    $this->SetStatus(self::STATUS_INST_DEVICE_NOT_FOUND);
                    return false;
                }

                if ($respSync = $this->SendCommand(self::SERVER_KNM, ['group' => 'register', 'command' => 'sync_judo_device', 'data' => $data])){
                    $resp = json_decode($respSync, true);
                    if ($resp['status'] !== 'ok'){
                        return false;
                    }
                }

                if ($this->ReadPropertyString(self::PROP_DEVICETYPE) == self::DT_I_SOFT_PLUS) {
                    $responseMyJudoCom_connect = $this->SendCommand(self::SERVER_JUDO, ['group'         => 'register',
                                                                                        'parameter'     => self::DT_I_SOFT_PLUS,
                                                                                        'serial number' => $serialnumber,
                                                                                        'command'       => 'connect']);

                    if ($responseMyJudoCom_connect === false){
                        return false;
                    }
                }

            }

            return true;
		}

        private function Sleep(int $time): void
        {
            $this->SendDebug(__FUNCTION__, 'Sleep requested for ' . $time . ' ms', 0);
            $this->SetTimerInterval("SleepTimer", $time);
            $this->SetTimerInterval("RefreshTimer", 0);
        }

        public function Wakeup(): void
        {
            $this->SendDebug(__FUNCTION__, 'Resuming regular activity.', 0);
            $this->SetTimerInterval("SleepTimer", 0);
            $refreshRate = $this->ReadPropertyInteger("RefreshRate");
            $this->SetTimerInterval("RefreshTimer", $refreshRate * 1000);
        }

		public function TestConnection(): bool
		{
			return $this->Login();
		}


        private function getInValue(array $deviceData, int $index = null, int $subIndex = null){
            $value = null;
            $data = $deviceData[$index]['data']??'';

            switch ($index){
                case 1:
                    $svMinor = intval(substr($data, 2, 2), 16);
                    $svMajor = intval(substr($data, 4, 2), 16);

                    if ($svMinor < 10) {
                        if ($svMinor === 0){
                            $minor = '0';
                        } else {
                            $minor = '0' . $svMinor;
                        }
                    } else {
                        $minor = $svMinor;
                    }

                    $value = $svMajor . '.' . $minor;
                    break;

                case 2:
                    $hvMinor = intval(substr($data, 0, 2), 16);
                    $hvMajor = intval(substr($data, 2, 2), 16);

                    if (($hvMinor > 0) && ($hvMinor < 10)){
                        $hvMinor = '0' . $hvMinor;
                    }

                    $value = $hvMajor . '.' . $hvMinor;
                    break;

                case 3:
                    if (strlen($data) === 8) {
                        $value = (string) hexdec($this->formatEndian(substr($data, 0, 8)));
                    } else {
                        $value = '';
                    }
                    break;

                case 7:
                    if (strlen($data) === 12) {
                        $v1 = hexdec($this->formatEndian(substr($data, 0, 4) . '0000'));
                        $v1 = intdiv($v1, 24);
                        $v2 = hexdec($this->formatEndian(substr($data, 4, 4) . '0000'));
                        $v3 = hexdec($this->formatEndian(substr($data, 8, 4) . '0000'));
                        $value = implode(':', [$v1, $v2, $v3]);
                    } else {
                        $value = $data;
                    }
                    break;

                case 8:
                    if (strlen($data) === 8) {
                        $value = hexdec($this->formatEndian(substr($data, 0, 8)));
                    } else {
                        $value = 0;
                    }
                    break;

                case 93:
                    if(strlen($data) === 10){
                        $kapazitaet = intval(substr($data, 6, 2),16);
                        $value = (string) $kapazitaet;
                    } else if(strlen($data) === 18){
                        $kapazitaet = intval(substr($data, 6, 2),16);
                        $sekunden = intval(substr($data, 10,2),16);
                        $minuten = intval(substr($data, 12,2),16);
                        $stunden = intval(substr($data, 14,2),16);
                        $value = implode(':', [$kapazitaet, $sekunden, $minuten, $stunden]);
                    } else {
                        $value = '0';
                    }
                    break;

                case 790:
                    $value = '';
                    if ((strlen($data) === 66) && !is_null($subIndex)) {
                        $data = explode(':', $data)[1];
                        switch ($subIndex) {

                                case 2:
                                    $value = hexdec(substr($data, 2, 2));
                                    $value = decbin($value);
                                    while (strlen($value) < 8){
                                        $value = '0' . $value;
                                    }
                                    break;


                                case 8:
                                case 10:
                                case 26:
                                    $value =intval(substr($data, $subIndex*2, 2), 16);
                                    break;

                            case 1617:
                                $value = hexdec($this->formatEndian(substr($data, 32, 4) . '0000'));
                                break;
                        }
                    }
                    break;

                case 791:
                    $value = '';
                    if (strlen($data) === 66) {
                        if (!is_null($subIndex)) {
                            $data = explode(':', $data)[1];
                            switch ($subIndex) {
                                case 3031:
                                    $tREGANZAHL_LO = substr($data, 60,2);
                                    $tREGANZAHL_HI = substr($data, 62,2);
                                    $value = intval($tREGANZAHL_HI . $tREGANZAHL_LO, 16);
                                    break;

                                // Statusflag Betrieb/Regeneration
                                case 0:
                                    $flag = intval(substr($data, $subIndex *2, 2),16);
                                    $flagBinary = decbin($flag);
                                    $this->SendDebug(__FUNCTION__, 'get_device_data 791, 0 (Regeneration): ' . $flagBinary, 0);
                                    $value = ($flagBinary !== '') ? $flagBinary[strlen($flagBinary) - 1] : 0;
                                    break;
                            }
                        }
                    }
                    break;

                case 792:
                    $value = '';
                    if (strlen($data) === 66) {
                        if (!is_null($subIndex)) {
                            $data = explode(':', $data)[1];
                            switch ($subIndex) {

                                case 0:
                                    $standby = intval(substr($data, $subIndex *2, 2),16);
                                    $standbyBinary = decbin($standby);
                                    $value = $standbyBinary;
                                    break;


                                case 9:
                                case 18:
                                case 19:
                                    $value = intval(substr($data, $subIndex *2, 2), 16);
                                    break;

                                case 1213:
                                    $maxDurchflussLow = substr($data, 24, 2);
                                    $maxDurchflussHigh = substr($data, 26, 2);
                                    $value = intval($maxDurchflussHigh . $maxDurchflussLow, 16);
                                    break;

                                case 1415:
                                    $maxMengeLow = substr($data, 28, 2);
                                    $maxMengeHigh = substr($data, 30, 2);
                                    $value = intval($maxMengeHigh . $maxMengeLow, 16);
                                    break;

                                case 1617:
                                    $maxZeitLow = substr($data, 32, 2);
                                    $maxZeitHigh = substr($data, 34, 2);
                                    $value = intval($maxZeitHigh . $maxZeitLow, 16);
                                    break;

                                default:
                                    trigger_error(sprintf('%s: index %s: invalid subindex (%s)', __FUNCTION__, $index, $subIndex), E_USER_ERROR);

                            }
                        }
                    }
                    break;
                case 94:
                    if (strlen($data) === 8) {
                        $salzstand = hexdec($this->formatEndian(substr($data, 0, 4) . '0000'));
                        $reichweite = hexdec($this->formatEndian(substr($data, 4, 4) . '0000'));
                        $value = implode(':', [$salzstand, $reichweite]);
                    } else {
                        $value = $data;
                    }
                    break;

            }

            //echo sprintf('value: %s', $value). PHP_EOL. PHP_EOL;
            return $value;
        }
		private function formatEndian($endian, $format = 'N'): string {
			$endian = intval($endian, 16);      // convert string to hex
			$endian = pack('L', $endian);       // pack hex to binary sting (unsinged long, machine byte order)
			$endian = unpack($format, $endian); // convert binary sting to specified endian format

			return sprintf("%'.08x", $endian[1]); // return endian as a hex string (with padding zero)
		}

		private function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize): void
		{
				if (!IPS_VariableProfileExists($Name))
				{
					IPS_CreateVariableProfile($Name, VARIABLETYPE_INTEGER);
				}
				else
				{
					$profile = IPS_GetVariableProfile($Name);
					if ($profile['ProfileType'] !== VARIABLETYPE_INTEGER) {
                        throw new Exception("Variable profile type does not match for profile " . $Name);
                    }
				}
				IPS_SetVariableProfileIcon($Name, $Icon);
				IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
				IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
		}

		private function RegisterProfileFloat($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
		{
				if (!IPS_VariableProfileExists($Name))
				{
					IPS_CreateVariableProfile($Name, VARIABLETYPE_FLOAT);
				}
				elseif (IPS_GetVariableProfile($Name)['ProfileType'] !== VARIABLETYPE_FLOAT) {
                    throw new Exception("Variable profile type does not match for profile " . $Name);
                }
				IPS_SetVariableProfileIcon($Name, $Icon);
				IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
				IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
		}

        private function RegisterProfileBoolean($Name, $Icon, $Prefix, $Suffix, $Associations)
        {
            if (!IPS_VariableProfileExists($Name)) {
                IPS_CreateVariableProfile($Name, VARIABLETYPE_BOOLEAN);
            } else {
                $profile = IPS_GetVariableProfile($Name);
                if ($profile['ProfileType'] !== VARIABLETYPE_BOOLEAN) {
                    throw new Exception('Variable profile type does not match for profile ' . $Name);
                }
            }

            IPS_SetVariableProfileIcon($Name, $Icon);
            IPS_SetVariableProfileText($Name, $Prefix, $Suffix);

            foreach ($Associations as $Association) {
                IPS_SetVariableProfileAssociation($Name, (float) $Association[0], $Association[1], $Association[2], $Association[3]);
            }
        }


        private function updateIfNecessary($newValue, string $ident): void
		{
			$id = $this->GetIDForIdent($ident);
            $variableType = IPS_GetVariable($id)['VariableType'];
            if (in_array($variableType, [VARIABLETYPE_FLOAT, VARIABLETYPE_INTEGER]) && !is_numeric($newValue)){
                return;
            }
            if ($this->GetValue($ident) != $newValue){
                $this->SetValue($ident, $newValue);
				$this->SendDebug(__FUNCTION__, 'Updating variable ' . $ident . ' to value: ' . $newValue, 0);
			}
		}
	}