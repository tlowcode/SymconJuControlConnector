<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Webclient.php';
require_once __DIR__ . '/../libs/DebugHelper.php';

	class JuControlDevice extends IPSModule
	{

        //Status
        private const STATUS_INST_AUTHENTICATION_FAILED = 201;
        private const STATUS_INST_WRONG_DEVICETYPE  = 202;
        private const STATUS_INST_DEVICE_NOT_ONLINE = 203;

        //attributes
        private const ATTR_ACCESSTOKEN = 'AccessToken';
        private const ATTR_DEVICEDATA = 'DeviceData';

        //variable idents
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

        private const URL = 'https://www.myjudo.eu';

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$position = -1;

            //attributes
            $this->RegisterAttributeString(self::ATTR_ACCESSTOKEN, "noToken");
            $this->RegisterAttributeString(self::ATTR_DEVICEDATA, '');

			//timer
            $this->RegisterTimer("RefreshTimer", 0, 'JCD_RefreshData('. $this->InstanceID . ');');
            $this->RegisterTimer("SleepTimer", 0, 'JCD_Wakeup('. $this->InstanceID . ');');

			//properties
            $this->RegisterPropertyString("Username", "");
			$this->RegisterPropertyString("Password", "");
			$this->RegisterPropertyInteger("RefreshRate", 60);

            //profiles
            $this->RegisterProfileInteger("JCD.Days", "Clock", "", $this->Translate(' days'), 0, 0, 0);
            $this->RegisterProfileInteger("JCD.Liter", "Wave", "", $this->Translate(' liters'), 0, 99999999, 1);
            $this->RegisterProfileInteger("JCD.lph", "Drops", "", " l/h", 0, 0, 0);
            $this->RegisterProfileInteger("JCD.Minutes", "Clock", "", $this->Translate(' minutes'), 0, 0, 0);
            $this->RegisterProfileInteger("JCD.dH_int", "Drops", "", " °dH", 0, 50, 1);
            $this->RegisterProfileInteger("JCD.Hours", "Clock", "", $this->Translate(' hours'), 0, 10, 1);
            $this->RegisterProfileInteger('JCD.Minutes.WSMaxPeriodOfUse', 'Clock', '', $this->Translate(' minutes'), 0, 600, 10);
            $this->RegisterProfileInteger('JCD.Liters.WSMaxQuantity', '', '', " l", 0, 3000, 100);
            $this->RegisterProfileInteger('JCD.lph.WSMaxWaterFlow', '', '', " l/h", 0, 5000, 100);
            $this->RegisterProfileInteger('JCD.Waterscene', "Drops", "", "", 0, 4, 0);
            IPS_SetVariableProfileAssociation ('JCD.Waterscene', 0, $this->Translate('Normal mode'), 'Ok', 0x00FF00);
            IPS_SetVariableProfileAssociation ('JCD.Waterscene', 1, $this->Translate('Shower'), 'Shower', 0xFF9C00);
            IPS_SetVariableProfileAssociation ('JCD.Waterscene', 2, $this->Translate('Filling of heating'), 'Temperature', 0xFF9C00);
            IPS_SetVariableProfileAssociation ('JCD.Waterscene', 3, $this->Translate('Garden irrigation'), 'Drops', 0xFF9C00);
            IPS_SetVariableProfileAssociation ('JCD.Waterscene', 4, $this->Translate('Washing'), 'Pants', 0xFF9C00);
            $this->RegisterProfileInteger('JCD.WSHolidayMode', '', '', '', 0, 3, 0);
            IPS_SetVariableProfileAssociation ('JCD.WSHolidayMode', 0, $this->Translate('no holiday mode'), '', -1);
            IPS_SetVariableProfileAssociation ('JCD.WSHolidayMode', 1, $this->Translate('Holiday mode 1'), '', -1);
            IPS_SetVariableProfileAssociation ('JCD.WSHolidayMode', 2, $this->Translate('Holiday mode 2'), '', -1);
            IPS_SetVariableProfileAssociation ('JCD.WSHolidayMode', 3, $this->Translate('Shut off water'), '', -1);
            $this->RegisterProfileInteger('JCD.kg', '', '', ' kg', 0, 0, 0);
            $this->RegisterProfileFloat("JCD.dH_float", "Drops", "", " °dH", 0, 50, 0.1);
            $this->RegisterProfileBoolean('JCD.NoYes', '', '', '', [
                [false, $this->Translate('No'), '', -1],
                [true, $this->Translate('Yes'),  '', -1]
            ]);

            //variables
            $this->RegisterVariableString("deviceID", $this->Translate('Device number'), "", ++$position);
			$this->RegisterVariableString("deviceType", $this->Translate('Device type'), "", ++$position);
			$this->RegisterVariableString("deviceState", $this->Translate('State'), "", ++$position);
			$this->RegisterVariableString("deviceSN", $this->Translate('Serial number'), "", ++$position);

			$this->RegisterVariableInteger("targetHardness", $this->Translate('Desired water hardness'), "JCD.dH_int", ++$position);
			$this->RegisterVariableFloat("inputHardness", $this->Translate('Input water hardness'), "JCD.dH_float", ++$position);

			$this->RegisterVariableInteger(self::VAR_IDENT_RANGESALTPERCENT, $this->Translate('Fill level salt'), '~Intensity.100', ++$position);
			$this->RegisterVariableInteger(self::VAR_IDENT_RANGESALTDAYS, $this->Translate('Range salt storage'), 'JCD.Days', ++$position);

			$this->RegisterVariableInteger(self::VAR_IDENT_SALTLEVEL, $this->Translate('Salt storage'), 'JCD.kg', ++$position);

			$this->RegisterVariableInteger(self::VAR_IDENT_CURRENTFLOW,  $this->Translate('Water flow'), 'JCD.lph', ++$position);

            $this->RegisterVariableBoolean(self::VAR_IDENT_WATERSTOP, $this->Translate('Water stop'), '~Switch', ++$position);
            $this->EnableAction(self::VAR_IDENT_WATERSTOP);
            $this->RegisterVariableInteger(self::VAR_IDENT_WATERSTOP_MAXPERIODOFUSE, $this->Translate('Max. Period of Use'), 'JCD.Minutes.WSMaxPeriodOfUse', ++$position);
            $this->EnableAction(self::VAR_IDENT_WATERSTOP_MAXPERIODOFUSE);
            $this->RegisterVariableInteger(self::VAR_IDENT_WATERSTOP_MAXQUANTITY, $this->Translate('Max. Quantity'), 'JCD.Liters.WSMaxQuantity', ++$position);
            $this->EnableAction(self::VAR_IDENT_WATERSTOP_MAXQUANTITY);
            $this->RegisterVariableInteger(self::VAR_IDENT_WATERSTOP_MAXWATERFLOW, $this->Translate('Max. Water Flow'), 'JCD.lph.WSMaxWaterFlow', ++$position);
            $this->EnableAction(self::VAR_IDENT_WATERSTOP_MAXWATERFLOW);
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

			$this->RegisterVariableInteger(self::VAR_IDENT_ACTIVESCENE, $this->Translate('Active water scene'), "JCD.Waterscene", ++$position);
			$this->EnableAction(self::VAR_IDENT_ACTIVESCENE);

			$this->RegisterVariableString("swVersion", $this->Translate('Software version'), "", ++$position);
			$this->RegisterVariableString("hwVersion", $this->Translate('Hardware version'), "", ++$position);
			$this->RegisterVariableString("ccuVersion", $this->Translate('Connectivity module version'), "", ++$position);

			$this->RegisterVariableInteger("nextService", $this->Translate('Next service'), "JCD.Days", ++$position);
            $this->RegisterVariableBoolean("hasEmergencySupply", $this->Translate('Safety-Modul'), "JCD.NoYes", ++$position);

			$this->RegisterVariableInteger("totalWater", $this->Translate('Total water quantity'), "JCD.Liter", ++$position);
            $this->RegisterVariableBoolean(self::VAR_IDENT_REGENERATION, $this->Translate('Regeneration'), '~Switch', ++$position);
            $this->EnableAction(self::VAR_IDENT_REGENERATION);

			$this->RegisterVariableInteger("totalRegeneration", $this->Translate('Total regeneration rate'), "", ++$position);
			$this->RegisterVariableInteger("totalService", $this->Translate('Number of services'), "", ++$position);

            $this->RegisterVariableInteger(self::VAR_IDENT_HARDNESS_NORMAL, $this->Translate('Water scene hardness \'Normal\''), 'JCD.dH_int', ++$position);
            $this->EnableAction(self::VAR_IDENT_HARDNESS_NORMAL);
			$this->RegisterVariableInteger(self::VAR_IDENT_HARDNESS_WASHING,  $this->Translate('Water scene hardness \'Washing\''), 'JCD.dH_int', ++$position);
			$this->EnableAction(self::VAR_IDENT_HARDNESS_WASHING);
			$this->RegisterVariableInteger(self::VAR_IDENT_HARDNESS_HEATER, $this->Translate('Water scene hardness \'Filling of heating\''), 'JCD.dH_int', ++$position);
			$this->EnableAction(self::VAR_IDENT_HARDNESS_HEATER);
            $this->RegisterVariableInteger(self::VAR_IDENT_HARDNESS_WATERING, $this->Translate('Water scene hardness \'Garden irrigation\''), 'JCD.dH_int', ++$position);
			$this->EnableAction(self::VAR_IDENT_HARDNESS_WATERING);
            $this->RegisterVariableInteger(self::VAR_IDENT_HARDNESS_SHOWER, $this->Translate('Water scene hardness \'Shower\''), 'JCD.dH_int', ++$position);
			$this->EnableAction(self::VAR_IDENT_HARDNESS_SHOWER);

            $this->RegisterVariableInteger(self::VAR_IDENT_TIME_WASHING, $this->Translate('Water scene time \'Washing\''), 'JCD.Hours', ++$position);
            $this->EnableAction(self::VAR_IDENT_TIME_WASHING);
            $this->RegisterVariableInteger(self::VAR_IDENT_TIME_HEATER, $this->Translate('Water scene time \'Filling of heating\''), 'JCD.Hours', ++$position);
            $this->EnableAction(self::VAR_IDENT_TIME_HEATER);
            $this->RegisterVariableInteger(self::VAR_IDENT_TIME_WATERING, $this->Translate('Water scene time \'Garden irrigation\''), 'JCD.Hours', ++$position);
            $this->EnableAction(self::VAR_IDENT_TIME_WATERING);
            $this->RegisterVariableInteger(self::VAR_IDENT_TIME_SHOWER, $this->Translate('Water scene time \'Shower\''), 'JCD.Hours', ++$position);
            $this->EnableAction(self::VAR_IDENT_TIME_SHOWER);

			$this->RegisterVariableInteger("remainingTime", $this->Translate('Remaining time scene'), "JCD.Minutes", ++$position);

			$this->SetStatus(IS_INACTIVE);

		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();
			$this->Login();
		}

		/* wird aufgerufen, wenn eine Variable geändert wird */
		public function RequestAction($Ident, $Value) {

            $this->SendDebug(__FUNCTION__, sprintf('Ident: %s, Value: %s', $Ident, $Value), KL_NOTIFY);

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
				$deviceCommandUrl = self::URL . '/interface/?token=' . $this->ReadAttributeString(self::ATTR_ACCESSTOKEN)
				. $strSerialnumber . $this->GetValue('deviceSN')
				. '&group=register&command=' 
				. $command;
                if (isset($parameter)){
                    $deviceCommandUrl .= '&parameter=' . $parameter;
                }

				$this->SendDebug(__FUNCTION__, 'Requesting API URL '. $deviceCommandUrl, KL_DEBUG);
                $wc = new WebClient();
				$response = $wc->Navigate($deviceCommandUrl);
				$json = json_decode($response, false);
				$this->SendDebug(__FUNCTION__, 'Received response from API: '. $response, KL_DEBUG);

				if(isset($json->status) && $json->status === 'ok') {
					$this->SetValue($Ident, $Value);
				} else {
					$this->SendDebug(__FUNCTION__, 'Error during request to JuControl API, ', KL_ERROR);
				}
			} else {
                trigger_error(__FUNCTION__ .': no command set', E_USER_ERROR);
            }

            $this->Sleep(10000);

		}

		public function RefreshData(): bool
		{
			try {
				$wc = new WebClient();
				$deviceDataUrl = self::URL . '/interface/?token=' . $this->ReadAttributeString(self::ATTR_ACCESSTOKEN) . '&group=register&command=get%20device%20data';
				$response = $wc->Navigate($deviceDataUrl);
	
				if ($response === FALSE) {
				    //$this->SetStatus(104);
				    //$this->SetTimerInterval("RefreshTimer", 0);
				    $this->SendDebug(__FUNCTION__, 'Error during request to JuControl API: '. $deviceDataUrl, KL_ERROR);
                    return false;
				}

                $json = json_decode($response, false);

                if (isset($json->status) && $json->status === 'ok')
                {
                    $this->SendDebug(__FUNCTION__, 'get_device_data: ' . $response, KL_DEBUG);

                    /* Parse response */

                    /* Device online */
                    if ($json->data[0]->status !== 'online')
                    {
                        $this->SetStatus(self::STATUS_INST_DEVICE_NOT_ONLINE);
                        return false;
                    }

/*                     /* Device Type
                    if ($json->data[0]->data[0]->dt === '0x33')
                    { */
                        $this->SetStatus(IS_ACTIVE);
                        $this->updateIfNecessary('i-soft safe', "deviceType");
/*                     }
                    else
                    {
                        $this->SetStatus(self::STATUS_INST_WRONG_DEVICETYPE);
                        $this->SendDebug(__FUNCTION__, 'Wrong device type (' . $json->data[0]->data[0]->dt . ') found -> Aborting!', KL_ERROR);
                        $this->SetTimerInterval("RefreshTimer", 0);
                        return false;
                    } */

                    /* Device S/N */
                    $this->updateIfNecessary($json->data[0]->serialnumber, "deviceSN");

                    /* Device state */
                    $this->updateIfNecessary($json->data[0]->status, "deviceState");

                    /* Connectivity module version */
                    $this->updateIfNecessary($json->data[0]->sv, "ccuVersion");


                    $deviceData = json_decode($response, true)['data'][0]['data'][0]['data'];
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
                    switch ($json->data[0]->waterscene) {
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
                    $this->updateIfNecessary($this->getInValue($deviceData, 1), "swVersion");

                    /* HW Version */
                    $this->updateIfNecessary($this->getInValue($deviceData, 2) , "hwVersion");

                    /* Device ID */
                    $this->updateIfNecessary($this->getInValue($deviceData, 3), "deviceID");

                    /* Service Info*/
                    $infoService = explode (':', $this->getInValue($deviceData, 7));
                    if (isset($infoService[0])){
                        $this->updateIfNecessary((int) $infoService[0], "nextService");
                    }
                    if (isset($infoService[1])) {
                        $this->updateIfNecessary((int)$infoService[1], "totalService");
                    }

                    /* Total water*/
                    $this->updateIfNecessary($this->getInValue($deviceData, 8), "totalWater");

                    /* Count regeneration */
                    $totalRegeneration = $this->getInValue($deviceData, 791, 3031);
                    if ($totalRegeneration !== ''){
                        $this->updateIfNecessary($totalRegeneration, "totalRegeneration");
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
                    $this->updateIfNecessary($inputHardness, "inputHardness");

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


                    /* read target hardness of waterscenes */
                    $this->updateIfNecessary((int) $json->data[0]->hardness_washing, self::VAR_IDENT_HARDNESS_WASHING);
                    $this->updateIfNecessary((int) $json->data[0]->hardness_shower, self::VAR_IDENT_HARDNESS_SHOWER);
                    $this->updateIfNecessary((int) $json->data[0]->hardness_watering, self::VAR_IDENT_HARDNESS_WATERING);
                    $this->updateIfNecessary((int) $json->data[0]->hardness_heater, self::VAR_IDENT_HARDNESS_HEATER);
                    $this->updateIfNecessary((int) $json->data[0]->waterscene_normal, self::VAR_IDENT_HARDNESS_NORMAL);

                    /* read times of waterscenes */
                    if (isset($json->data[0]->waterscene_time)){
                        $this->updateIfNecessary((int) $json->data[0]->waterscene_time, self::VAR_IDENT_TIME_SHOWER);
                    }
                    if (isset($json->data[0]->waterscene_time_garden)){
                        $this->updateIfNecessary((int) $json->data[0]->waterscene_time_garden, self::VAR_IDENT_TIME_WATERING);
                    }
                    if (isset($json->data[0]->waterscene_time_heater)){
                        $this->updateIfNecessary((int) $json->data[0]->waterscene_time_heater, self::VAR_IDENT_TIME_HEATER);
                    }
                    if (isset($json->data[0]->waterscene_time_washing)){
                        $this->updateIfNecessary((int) $json->data[0]->waterscene_time_washing, self::VAR_IDENT_TIME_WASHING);
                    }

                    /* read target hardness */
                    $this->updateIfNecessary($this->getInValue($deviceData, 790, 8), "targetHardness");


                    /* Remaining time of active water scene */
                    if($this->GetValue(self::VAR_IDENT_ACTIVESCENE) !== 0)
                    {
                        if($json->data[0]->disable_time !== '')
                        {
                            $remainingTime = (((int)$json->data[0]->disable_time - time()) / 60) + 1;
                            $this->updateIfNecessary(max((int) $remainingTime, 0), "remainingTime");
							/* update target hardness due to active waterscene */
							switch ($this->GetValue(self::VAR_IDENT_ACTIVESCENE)) {
								case '1':
									$this->updateIfNecessary((int) $json->data[0]->hardness_shower, "targetHardness");
									break;
								case '2':
									$this->updateIfNecessary((int) $json->data[0]->hardness_heater, "targetHardness");
									break;
								case '3':
									$this->updateIfNecessary((int) $json->data[0]->hardness_watering, "targetHardness");
									break;
								case '4':
									$this->updateIfNecessary((int) $json->data[0]->hardness_washing, "targetHardness");
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
                else
                {
                    /* Token not valid -> try to log in again one time and wait for next RefreshData! */
                    $this->Login();
                }

            }
			catch(Exception $e){
				$this->SendDebug(__FUNCTION__, 'Error during data crawling: '. $e->getMessage(), KL_ERROR);
			}

			return true;
		
		}

		private function Login(): bool
        {

			$wc = new WebClient();

			$username = $this->ReadPropertyString("Username");
			$passwd = $this->ReadPropertyString("Password");

			$loginUrl = self::URL . '/interface/?group=register&command=login&name=login&user=' . $username . '&password=' . md5($passwd, false) . '&nohash=' . $passwd . '&role=customer';
		
			$this->SendDebug(__FUNCTION__, 'Trying to log in with username: '. $username, KL_MESSAGE);


			$response = $wc->Navigate($loginUrl);
			if ($response === FALSE) 
			{
				$this->SetStatus(self::STATUS_INST_AUTHENTICATION_FAILED);
                return false;
			}

            $json = json_decode($response, false);
            if (isset($json->status) && $json->status === 'ok')
            {
                $this->SendDebug(__FUNCTION__, 'Login successful, Token: '. $json->token, KL_MESSAGE);
                $this->WriteAttributeString(self::ATTR_ACCESSTOKEN, $json->token);
                $this->SetStatus(IS_ACTIVE);

                $refreshRate = $this->ReadPropertyInteger("RefreshRate");
                $this->SetTimerInterval("RefreshTimer", $refreshRate * 1000);
            }
            else
            {
                $this->SendDebug(__FUNCTION__, 'Login failed!', KL_ERROR);
                $this->SetStatus(self::STATUS_INST_AUTHENTICATION_FAILED);
                $this->SetTimerInterval("RefreshTimer", 0);
            }

            return true;
		}

        private function Sleep(int $time)
        {
            $this->SendDebug(__FUNCTION__, 'Sleep requested for ' . (string) $time . ' ms', KL_MESSAGE);
            $this->SetTimerInterval("SleepTimer", $time);
            $this->SetTimerInterval("RefreshTimer", 0);
        }

        public function Wakeup()
        {
            $this->SendDebug(__FUNCTION__, 'Resuming regular activity.', KL_MESSAGE);
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
                                    $this->SendDebug(__FUNCTION__, 'get_device_data 791, 0 (Regeneration): ' . $flagBinary, KL_NOTIFY);
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

		private function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
		{
				if (!IPS_VariableProfileExists($Name))
				{
					IPS_CreateVariableProfile($Name, VARIABLETYPE_INTEGER);
				}
				else
				{
					$profile = IPS_GetVariableProfile($Name);
					if ($profile['ProfileType'] !== VARIABLETYPE_INTEGER)
						throw new Exception("Variable profile type does not match for profile " . $Name);
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
				else
				{
					$profile = IPS_GetVariableProfile($Name);
					if ($profile['ProfileType'] !== VARIABLETYPE_FLOAT)
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
                if ($profile['ProfileType'] != VARIABLETYPE_BOOLEAN) {
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
				$this->SendDebug(__FUNCTION__, 'Updating variable ' . $ident . ' to value: ' . $newValue, KL_NOTIFY);
			}
		}
	}