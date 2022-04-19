<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Webclient.php';
require_once __DIR__ . '/../libs/DebugHelper.php';

	class JuControlDevice extends IPSModule
	{

        //variable idents
        private const VAR_IDENT_BATTERYSTATE = 'batteryState';
        private const VAR_IDENT_BATTERYRUNTIME = 'batteryRuntime';
        private const VAR_IDENT_CURRENTFLOW = 'currentFlow';
        private const VAR_IDENT_WATERSTOP = 'waterStop';
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

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$position = -1;

            $this->RegisterAttributeString("AccessToken", "noToken");

			$this->RegisterTimer("RefreshTimer", 0, 'JCD_RefreshData('. $this->InstanceID . ');');	

			$this->RegisterPropertyString("Username", "");
			$this->RegisterPropertyString("Password", "");
			$this->RegisterPropertyInteger("RefreshRate", 60);
			$this->RegisterPropertyInteger("TimeShower", 60);
			$this->RegisterPropertyInteger("TimeHeating", 60);
			$this->RegisterPropertyInteger("TimeWatering", 60);
			$this->RegisterPropertyInteger("TimeWashing", 60);

            //profiles
            $this->RegisterProfileInteger("JCD.Days", "Clock", "", " Tage", 0, 0, 0);
            $this->RegisterProfileInteger("JCD.lph", "Drops", "", " l/h", 0, 0, 0);
            $this->RegisterProfileInteger("JCD.Minutes", "Clock", "", " Minuten", 0, 0, 0);
            $this->RegisterProfileInteger("JCD.dH_int", "Drops", "", " °dH", 0, 50, 1);
            $this->RegisterProfileInteger("JCD.Hours", "Clock", "", " Stunden", 0, 10, 1);

            $this->RegisterProfileFloat("JCD.dH_float", "Drops", "", " °dH", 0, 50, 0.1);


            $this->RegisterVariableString("deviceID", "Geräte-ID", "", ++$position);
			$this->RegisterVariableString("deviceType", "Geräte-Typ", "", ++$position);
			$this->RegisterVariableString("deviceState", "Status", "", ++$position);
			$this->RegisterVariableString("deviceSN", "Seriennummer", "", ++$position);


			$this->RegisterVariableInteger("targetHardness", "Ziel-Wasserhärte", "JCD.dH_int", ++$position);
			$this->RegisterVariableFloat("inputHardness", "Ist-Wasserhärte", "JCD.dH_float", ++$position);


			$this->RegisterVariableInteger(self::VAR_IDENT_RANGESALTPERCENT, 'Füllstand Salz', '~Intensity.100', ++$position);
			$this->RegisterVariableInteger(self::VAR_IDENT_RANGESALTDAYS, 'Reichweite Salzvorrat', 'JCD.Days', ++$position);

			$this->RegisterProfileInteger('JCD.kg', '', '', ' kg', 0, 0, 0);
			$this->RegisterVariableInteger(self::VAR_IDENT_SALTLEVEL, 'Salzvorrat', 'JCD.kg', ++$position);

			$this->RegisterVariableInteger(self::VAR_IDENT_CURRENTFLOW, 'Aktueller Durchfluss', 'JCD.lph', ++$position);
			$this->RegisterVariableBoolean(self::VAR_IDENT_WATERSTOP, 'Wasserstop', '~Switch', ++$position);
            $this->EnableAction(self::VAR_IDENT_WATERSTOP);


			$this->RegisterVariableInteger(self::VAR_IDENT_BATTERYSTATE, 'Batteriezustand Notstrommodul', '~Intensity.100', ++$position);
			$this->RegisterVariableString(self::VAR_IDENT_BATTERYRUNTIME, 'BatteryRuntime (H:MM:SS)', '', ++$position);

			$this->RegisterProfileInteger("JCD.Waterscene", "Drops", "", "", 0, 4, 0);
			IPS_SetVariableProfileAssociation ("JCD.Waterscene", 0, "Normal", "Ok", 0x00FF00);
			IPS_SetVariableProfileAssociation ("JCD.Waterscene", 1, "Duschen", "Shower", 0xFF9C00);
			IPS_SetVariableProfileAssociation ("JCD.Waterscene", 2, "Heizungsfüllung", "Temperature", 0xFF9C00);
			IPS_SetVariableProfileAssociation ("JCD.Waterscene", 3, "Bewässerung", "Drops	", 0xFF9C00);
			IPS_SetVariableProfileAssociation ("JCD.Waterscene", 4, "Waschen", "Pants", 0xFF9C00);

			$this->RegisterVariableInteger(self::VAR_IDENT_ACTIVESCENE, "Aktive Wasserszene", "JCD.Waterscene", ++$position);
			$this->EnableAction(self::VAR_IDENT_ACTIVESCENE);

			$this->RegisterVariableString("swVersion", "SW Version", "", ++$position);
			$this->RegisterVariableString("hwVersion", "HW Version", "", ++$position);
			$this->RegisterVariableString("ccuVersion", "CCU Version", "", ++$position);

			$this->RegisterVariableInteger("nextService", "Tage bis zur Wartung", "JCD.Days", ++$position);

            //$this->RegisterProfileBool
            $this->RegisterProfileBoolean('JCD.NoYes', '', '', '', [
                [false, $this->Translate('No'), '', -1],
                [true, $this->Translate('Yes'),  '', -1]
            ]);

            $this->RegisterVariableBoolean("hasEmergencySupply", "Notstrommodul verbaut", "JCD.NoYes", ++$position);

			$this->RegisterProfileInteger("JCD.Liter", "Wave", "", " Liter", 0, 99999999, 1);
			$this->RegisterVariableInteger("totalWater", "Gesamt-Durchfluss", "JCD.Liter", ++$position);

			$this->RegisterVariableInteger("totalRegeneration", "Gesamt-Regenerationen", "", ++$position);
			$this->RegisterVariableInteger("totalService", "Gesamt-Wartungen", "", ++$position);

			$this->RegisterVariableInteger(self::VAR_IDENT_HARDNESS_WASHING, 'Szenen-Wasserhärte Waschen', 'JCD.dH_int', ++$position);
			$this->EnableAction(self::VAR_IDENT_HARDNESS_WASHING);

			$this->RegisterVariableInteger(self::VAR_IDENT_HARDNESS_HEATER, 'Szenen-Wasserhärte Heizung', 'JCD.dH_int', ++$position);
			$this->EnableAction(self::VAR_IDENT_HARDNESS_HEATER);

            $this->RegisterVariableInteger(self::VAR_IDENT_HARDNESS_WATERING, 'Szenen-Wasserhärte Bewässerung', 'JCD.dH_int', ++$position);
			$this->EnableAction(self::VAR_IDENT_HARDNESS_WATERING);

            $this->RegisterVariableInteger(self::VAR_IDENT_HARDNESS_SHOWER, 'Szenen-Wasserhärte Duschen', 'JCD.dH_int', ++$position);
			$this->EnableAction(self::VAR_IDENT_HARDNESS_SHOWER);

            $this->RegisterVariableInteger(self::VAR_IDENT_HARDNESS_NORMAL, 'Szenen-Wasserhärte Normal', 'JCD.dH_int', ++$position);
            $this->EnableAction(self::VAR_IDENT_HARDNESS_NORMAL);

            $this->RegisterVariableInteger(self::VAR_IDENT_TIME_WASHING, 'Szenen-Dauer Waschen', 'JCD.Hours', ++$position);
            $this->EnableAction(self::VAR_IDENT_TIME_WASHING);

            $this->RegisterVariableInteger(self::VAR_IDENT_TIME_HEATER, 'Szenen-Dauer Heizung', 'JCD.Hours', ++$position);
            $this->EnableAction(self::VAR_IDENT_TIME_HEATER);

            $this->RegisterVariableInteger(self::VAR_IDENT_TIME_WATERING, 'Szenen-Dauer Bewässerung', 'JCD.Hours', ++$position);
            $this->EnableAction(self::VAR_IDENT_TIME_WATERING);

            $this->RegisterVariableInteger(self::VAR_IDENT_TIME_SHOWER, 'Szenen-Dauer Duschen', 'JCD.Hours', ++$position);
            $this->EnableAction(self::VAR_IDENT_TIME_SHOWER);

			$this->RegisterVariableInteger("remainingTime", "Restlaufzeit Szene", "JCD.Minutes", ++$position);

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

			$wc = new WebClient();
			$url = 'https://www.myjudo.eu';
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
                case self::VAR_IDENT_HARDNESS_NORMAL:
					$command = "write%20data&dt=0x33&index=60&data=". $Value . "&da=0x1&&action=normal";
					break;

                case self::VAR_IDENT_WATERSTOP:
                    if ($Value){
                       $command = "write%20data&dt=0x33&index=72&data=&da=0x1";
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
							$command = "write%20data&dt=0x33&index=202&data=" . $hardness . "&da=0x1&disable_time=". (time() + $time*60) . "&action=" . $action;
							break;
						case 2:
							$action = "heaterfilling";
							$time = $this->GetValue(self::VAR_IDENT_TIME_HEATER);
                            $hardness = $this->GetValue(self::VAR_IDENT_HARDNESS_HEATER);
							$command = "write%20data&dt=0x33&index=204&data=" . $hardness . "&da=0x1&disable_time=". (time() + $time*60) . "&action=" . $action;
							break;
						case 3:
							$action = "watering";
							$time = $this->GetValue(self::VAR_IDENT_TIME_WATERING);
                            $hardness = $this->GetValue(self::VAR_IDENT_HARDNESS_WATERING);
							$command = "write%20data&dt=0x33&index=203&data=" . $hardness . "&da=0x1&disable_time=". (time() + $time*60) . "&action=" . $action;
							break;
						case 4:
							$action = "washing";
							$time = $this->GetValue(self::VAR_IDENT_TIME_WASHING);
                            $hardness =  $this->GetValue(self::VAR_IDENT_HARDNESS_WASHING);
							$command = "write%20data&dt=0x33&index=205&data=" . $hardness . "&da=0x1&disable_time=". (time() + $time*60) . "&action=" . $action;
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
				$deviceCommandUrl = $url 
				. '/interface/?token=' . $this->ReadAttributeString("AccessToken") 
				. $strSerialnumber . $this->GetValue('deviceSN')
				. '&group=register&command=' 
				. $command;
                if (isset($parameter)){
                    $deviceCommandUrl .= '&parameter=' . $parameter;
                }

				$this->SendDebug(__FUNCTION__, 'Requesting API URL '. $deviceCommandUrl, 0);
				$response = $wc->Navigate($deviceCommandUrl);
				$json = json_decode($response, false);
				$this->SendDebug(__FUNCTION__, 'Received response from API: '. $response, 0);

				if(isset($json->status) && $json->status === 'ok') {
					$this->SetValue($Ident, $Value);
				} else {
					$this->SendDebug(__FUNCTION__, 'Error during request to JuControl API, ', 0);
				}
			} else {
                trigger_error(__FUNCTION__ .': no command set', E_USER_ERROR);
            }

			$this->RefreshData();

		}

		public function RefreshData()
		{
			try {
				$wc = new WebClient();
				$url = 'https://www.myjudo.eu';
				$deviceDataUrl = $url . '/interface/?token=' . $this->ReadAttributeString("AccessToken") . '&group=register&command=get%20device%20data';
				$response = $wc->Navigate($deviceDataUrl);
	
				if ($response === FALSE) {
				    //$this->SetStatus(104);
				    //$this->SetTimerInterval("RefreshTimer", 0);
				    $this->SendDebug(__FUNCTION__, 'Error during request to JuControl API: '. $deviceDataUrl, 0);
				}
				else {
					$json = json_decode($response, false);

                    if (isset($json->status) && $json->status === 'ok')
					{
                        $this->SendDebug(__FUNCTION__, 'get_device_data: ' . $response, 0);

						/* Parse response */

                        /* Device Type */
                        if ($json->data[0]->data[0]->dt === '0x33')
						{
                            $this->SetStatus(IS_ACTIVE);
							$this->updateIfNecessary('i-soft safe', "deviceType");
						}
						else
						{
							$this->SetStatus(202);
							$this->SendDebug(__FUNCTION__, 'Wrong device type (' . $json->data[0]->data[0]->dt . ') found -> Aborting!', 0);
							$this->SetTimerInterval("RefreshTimer", 0);
						}
			
						/* Device S/N */
						$this->updateIfNecessary($json->data[0]->serialnumber, "deviceSN");
				
						/* Device state */
						$this->updateIfNecessary($json->data[0]->status, "deviceState");

						/* Connectivity module version */
						$this->updateIfNecessary($json->data[0]->sv, "ccuVersion");


                        $deviceData = json_decode($response, true)['data'][0]['data'][0]['data'];

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
                        $this->updateIfNecessary((int) $infoService[0], "nextService");
                        $this->updateIfNecessary((int) $infoService[1], "totalService");

                        /* Total water*/
						$this->updateIfNecessary($this->getInValue($deviceData, 8), "totalWater");

						/* Count regeneration */
                        $this->updateIfNecessary($this->getInValue($deviceData, 791, 3031), "totalRegeneration");

						/* Salt Info*/
						$saltInfo = explode(':', $this->getInValue($deviceData, 94));

						$SaltLevel = $saltInfo[0] / 1000; //Salzgewicht in kg
						$SaltLevelPercent = (int) (2 * $SaltLevel);
                        $SaltRange = $saltInfo[1]; //Salzreichweite in Tagen
						$this->updateIfNecessary($SaltLevelPercent, self::VAR_IDENT_RANGESALTPERCENT);
						$this->updateIfNecessary($SaltLevel, self::VAR_IDENT_SALTLEVEL);
						$this->updateIfNecessary($SaltRange, self::VAR_IDENT_RANGESALTDAYS);

                        /* Input hardness */
						$this->updateIfNecessary($this->getInValue($deviceData, 790, 26), "inputHardness");

						/* currentFlow */
						$this->updateIfNecessary($this->getInValue($deviceData, 790, 1617), self::VAR_IDENT_CURRENTFLOW);

						/* water stop */
                        $leckageschutzStatusflag = $this->getInValue($deviceData, 792, 0);
                        if (strlen($leckageschutzStatusflag) === 8) {
                            $wasserstop = (boolean) $leckageschutzStatusflag[0];
                        } else {
                            $wasserstop = false;
                        }
                        $this->updateIfNecessary($wasserstop, self::VAR_IDENT_WATERSTOP);

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

                        /*
                        echo sprintf('8 - Anzeige resthärte: %s', $this->getInValue($deviceData, 790, 8)) . PHP_EOL;
                        echo sprintf('10 - Anzeige rohhärte / aktuelle Rohwasserhärte: %s', $this->getInValue($deviceData, 790, 10)) . PHP_EOL;
                        echo sprintf('26 - Anzeige rohhärte / Rohwasserhärte1 in °dH: %s', $this->getInValue($deviceData, 790, 26)) . PHP_EOL;
                        echo sprintf('1617 - Wasserdurchfluss: %s', $this->getInValue($deviceData, 790, 1617)) . PHP_EOL;
                        */

						/* Remaining time of active water scene */
						if($this->GetValue(self::VAR_IDENT_ACTIVESCENE) !== 0)
						{
							if($json->data[0]->disable_time !== '')
							{
								$remainingTime = (((int)$json->data[0]->disable_time - time()) / 60) + 1;
								$this->updateIfNecessary(max((int) $remainingTime, 0), "remainingTime");
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
			}
			catch(Exception $e){
				$this->SendDebug(__FUNCTION__, 'Error during data crawling: '. $e->getMessage(), 0);
			}

			
		
			//$this->Send('GET', $loginUrl, '', 5000);

			//IPS_LogMessage($_IPS['SELF'], 'RefreshData() called! Username: '. $username . 'PW: ' . $passwd . 'URL: ' . $loginUrl);
		}

		public function Login(){

			$wc = new WebClient();
			$url = 'https://www.myjudo.eu';

			$username = $this->ReadPropertyString("Username");
			$passwd = $this->ReadPropertyString("Password");

			$loginUrl = $url . '/interface/?group=register&command=login&name=login&user=' . $username . '&password=' . md5($passwd, false) . '&nohash=' . $passwd . '&role=customer';
		
			$this->SendDebug(__FUNCTION__, 'Trying to log in with username: '. $username, 0);


			$response = $wc->Navigate($loginUrl);
			if ($response === FALSE) 
			{
				$this->SetStatus(201);
			}
			else 
			{
				$json = json_decode($response, false);
				if (isset($json->status) && $json->status === 'ok')
				{
					$this->SendDebug(__FUNCTION__, 'Login successful, Token: '. $json->token, 0);
					$this->WriteAttributeString("AccessToken", $json->token);
					$this->SetStatus(IS_ACTIVE);
					
					$refreshRate = $this->ReadPropertyInteger("RefreshRate");
					$this->SetTimerInterval("RefreshTimer", $refreshRate * 1000);
				}
				else
				{
					$this->SendDebug(__FUNCTION__, 'Login failed!', 0);
					$this->SetStatus(201);
					$this->SetTimerInterval("RefreshTimer", 0);
				}
			}
		
		}

		public function TestConnection()
		{
			$this->Login();
		}

        /*
         * abgeleitet aus https://www.myjudo.eu/js/deviceDataConverter.js?version=1.214
         */
        private function getInValue(array $deviceData, int $index = null, int $subIndex = null){
            $value = null;
            $data = $deviceData[$index]['data']??'';

            //echo sprintf('Index: %s, subIndex: %s', $index, $subIndex) . PHP_EOL;
            //var_dump ( $deviceData[$index]);

            switch ($index){
                // SW - Version / Get SW_Version
                // 3 Bytes
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

                // HW - Version / Get_HW_Versionb
                // 2 Bytes
                case 2:
                    $hvMinor = intval(substr($data, 0, 2), 16);
                    $hvMajor = intval(substr($data, 2, 2), 16);

                    if (($hvMinor > 0) && ($hvMinor < 10)){
                        $hvMinor = '0' . $hvMinor;
                    }

                    $value = $hvMajor . '.' . $hvMinor;
                    break;

                // Gerätenummer / Get_JDO_SerialNo
                // 4 Bytes unsigned
                case 3:
                    if (strlen($data) === 8) {
                        $value = (string) hexdec($this->formatEndian(substr($data, 0, 8)));
                    } else {
                        $value = '';
                    }
                    break;

                // Stunden bis zur nächsten Wartung / Get_Service_Time
                // 6 Bytes unsigned
                // 16 bit Stunden bis zur nächsten Wartung
                // 16 bit Registrierte Wartungen
                // 16 bit Angeforderte Wartungen
                case 7:
                    if (strlen($data) === 12) {
                        $v1 = hexdec($this->formatEndian(substr($data, 0, 4) . '0000'));
                        $v1 = intdiv($v1, 24); //Umrechnung von Stunden in Tage

                        $v2 = hexdec($this->formatEndian(substr($data, 4, 4) . '0000'));

                        $v3 = hexdec($this->formatEndian(substr($data, 8, 4) . '0000'));

                        $value = implode(':', [$v1, $v2, $v3]);
                    } else {
                        $value = $data;
                    }
                    break;

                    // Gesamtwasserverbrauch / Get_TotalWater
                // 4 Byte
                case 8:
                    if (strlen($data) === 8) {
                        $value = hexdec($this->formatEndian(substr($data, 0, 8)));
                    } else {
                        $value = 0;
                    }
                    break;

                // UPS Status lesen / Get_UPS
                // 9 Byte
                //    1	8-Bit (unsigned)	UPS Version_LO
                //    2	8-Bit (unsigned)	UPS Version_HI
                //    3	8-Bit (flag)	UPS-STATUSÂ Â  Bit7=LOW_BATT ; BIT6=Batterietest_lÃ¤uft; Bit5= 0; 	   Bit4=Wiederholender Test mit gesetztem LowBatt;
                //    Bit3=RelaisOn;	   Bit2=Batteriebetrieb;	    Bit1=24VOK; Bit0=Notstrommodul vorhanden
                //    4	8-Bit (unsigned)	UPS (letzte gemessene) Batteriespannung in Prozent
                //    5	8-Bit (unsigned)	UPS	(letzte gemessene) Batteriespannung in 0,1V (*10)
                //    6					8-Bit (unsigned)	UPS Aktuelle Batterielaufzeit in Sekunden (wird nach Batteriewechsel gelÃ¶scht)
                //    7					8-Bit (unsigned)	UPS Aktuelle Batterielaufzeit in Minuten	(wird nach Batteriewechsel gelÃ¶scht)
                //    8					8-Bit (unsigned)	UPS Aktuelle Batterielaufzeit in Stunden  (wird nach Batteriewechsel gelÃ¶scht)
                //    9					8-Bit (unsigned)	UPS BatterieReplace Counter Anzahl Batteriewechsel

                // index 93 Byte 3 - BatteriekapazitÃ¤t
                case 93:
                    if(strlen($data) === 10){
                        $kapazitaet = intval(substr($data, 6, 2),16);
                        $value = (string) $kapazitaet;
                    } else if(strlen($data) === 18){
                        // i-soft 2019er
                        $kapazitaet = intval(substr($data, 6, 2),16);
                        $sekunden = intval(substr($data, 10,2),16);
                        $minuten = intval(substr($data, 12,2),16);
                        $stunden = intval(substr($data, 14,2),16);
                        $value = implode(':', [$kapazitaet, $sekunden, $minuten, $stunden]);
                    } else {
                        $value = '0';
                    }
                    break;

                // Auslesen der Datentabelle / Get_Tableread
                // 1 byte subcode (32 byte response)
                // SUBCODE 0
                case 790:
                    if (strlen($data) === 66) {
                        if (!is_null($subIndex)) {
                            $data = explode(':', $data)[1];
                            switch ($subIndex) {

                                //Notstrommodul Ja/Nein
                                case 2:
                                    $value = hexdec(substr($data, 2, 2));
                                    $value = decbin($value);
                                    while (strlen($value) < 8){
                                        $value = '0' . $value;
                                    }
                                    break;


                                case 8: // Anzeige resthärte
                                case 10: // Anzeige rohhärte / aktuelle Rohwasserhärte
                                case 26: // Anzeige rohhärte / Rohwasserhärte1 in °dH
                                    $value =intval(substr($data, $subIndex*2, 2), 16);
                                    break;

                                case 1617: // Wasserdurchfluss
                                    $value = hexdec($this->formatEndian(substr($data, 32, 4) . '0000'));
                                    break;
                            }
                        } else {
                            $value = '';
                        }
                    }
                    break;

                case 791:
                    $value = '';
                    if (strlen($data) === 66) {
                        if (!is_null($subIndex)) {
                            $data = explode(':', $data)[1];
                            switch ($subIndex) {
                                // Gesamt Regenerationszahl
                                case 3031:
                                    $tREGANZAHL_LO = substr($data, 60,2);
                                    $tREGANZAHL_HI = substr($data, 66,2);
                                    $value = intval($tREGANZAHL_HI . $tREGANZAHL_LO, 16);
                                    break;

/*
                                // Statusflag Betrieb/Regeneration
                                case 0:
                                    var flag = parseInt(data.slice(0, 2), 16);
                                    var flagBinary = (+flag).toString(2);
                                    var statusFlag = (flagBinary.length > 0) ? flagBinary[flagBinary.length - 1] : 0;
                                    value = statusFlag;
                                    break;
*/
                            }
                        }
                    }
                    break;

                // Wasserstop Daten
                case 792:
                    $value = '';
                    if (strlen($data) === 66) {
                        if (!is_null($subIndex)) {
                            $data = explode(':', $data)[1];
                            switch ($subIndex) {

                                // Wasserstop statusflag
                                case 0:
                                    $standby = intval(substr($data, $subIndex, 2),16);
                                    $standbyBinary = decbin($standby);
                                    $value = $standbyBinary;
                                    break;
                            }
                        }
                    }
                    break;

                // Absoluten Salzstand lesen / GET_SALT_Volume
                // 4 Byte
                // low(Salzgew) | high(Salzgew) | low(Reichweite) | high(Reichweite)
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
			if ($this->GetValue($ident) != $newValue)
			{
                $this->SetValue($ident, $newValue);
				$this->SendDebug(__FUNCTION__, 'Updating variable ' . $ident . ' to value: ' . $newValue, 0);
			}
		}
	}