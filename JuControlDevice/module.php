<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/Webclient.php';
require_once __DIR__ . '/../libs/DebugHelper.php';

	class JuControlDevice extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterAttributeString("AccessToken", "noToken");

			$this->RegisterTimer("RefreshTimer", 0, 'JCD_RefreshData('. $this->InstanceID . ');');	

			$this->RegisterPropertyString("Username", "");
			$this->RegisterPropertyString("Password", "");
			$this->RegisterPropertyInteger("RefreshRate", 60);
			$this->RegisterPropertyInteger("TimeShower", 60);
			$this->RegisterPropertyInteger("TimeHeating", 60);
			$this->RegisterPropertyInteger("TimeWatering", 60);
			$this->RegisterPropertyInteger("TimeWashing", 60);

			$this->RegisterVariableString("deviceID", "Geräte-ID", "", 0);
			$this->RegisterVariableString("deviceType", "Geräte-Typ", "", 1);
			$this->RegisterVariableString("deviceState", "Status", "", 2);
			$this->RegisterVariableString("deviceSN", "Seriennummer", "", 3);


			$this->RegisterProfileInteger("JCD.dH_int", "Drops", "", " °dH", 0, 50, 1);
			$this->RegisterProfileFloat("JCD.dH_float", "Drops", "", " °dH", 0, 50, 0.1);

			$this->RegisterVariableInteger("targetHardness", "Ziel-Wasserhärte", "JCD.dH_int", 4);
			$this->RegisterVariableFloat("inputHardness", "Ist-Wasserhärte", "JCD.dH_float", 5);


			$this->RegisterProfileInteger("JCD.Percent", "Intensity", "", " %", 0, 100, 1);
			$this->RegisterVariableInteger("rangeSaltPercent", "Füllstand Salz", "JCD.Percent", 6);

			$this->RegisterProfileInteger("JCD.lph", "Drops", "", " l/h", 0, 1000000, 1);
			$this->RegisterVariableInteger("currentFlow", "Aktueller Durchfluss", "JCD.lph", 7);


			$this->RegisterVariableInteger("batteryState", "Batteriezustand Notstrommodul", "JCD.Percent", 8);

			$this->RegisterProfileInteger("JCD.Waterscene", "Drops", "", "", 0, 10, 1);
			IPS_SetVariableProfileAssociation ("JCD.Waterscene", 0, "Normal", "Ok", 0x00FF00);
			IPS_SetVariableProfileAssociation ("JCD.Waterscene", 1, "Duschen", "Shower", 0xFF9C00);
			IPS_SetVariableProfileAssociation ("JCD.Waterscene", 2, "Heizungsfüllung", "Temperature", 0xFF9C00);
			IPS_SetVariableProfileAssociation ("JCD.Waterscene", 3, "Bewässerung", "Drops	", 0xFF9C00);
			IPS_SetVariableProfileAssociation ("JCD.Waterscene", 4, "Waschen", "Pants", 0xFF9C00);


			$this->RegisterVariableInteger("activeScene", "Aktive Wasserszene", "JCD.Waterscene", 9);
			$this->EnableAction("activeScene");


			$this->RegisterVariableString("swVersion", "SW Version", "", 10);
			$this->RegisterVariableString("hwVersion", "HW Version", "", 11);
			$this->RegisterVariableString("ccuVersion", "CCU Version", "", 12);

			$this->RegisterProfileInteger("JCD.Days", "Clock", "", " Tage", 0, 10000, 1);

			$this->RegisterVariableInteger("nextService", "Tage bis zur Wartung", "JCD.Days", 13);
			$this->RegisterVariableString("hasEmergencySupply", "Notstrommodul verbaut", "", 14);

			$this->RegisterProfileInteger("JCD.Liter", "Wave", "", " Liter", 0, 99999999, 1);
			$this->RegisterVariableInteger("totalWater", "Gesamt-Durchfluss", "JCD.Liter", 15);

			$this->RegisterVariableString("totalRegeneration", "Gesamt-Regenerationen", "", 16);
			$this->RegisterVariableString("totalService", "Gesamt-Wartungen", "", 17);

			$this->RegisterVariableInteger("Hardness_Washing", "Szenen-Wasserhärte Waschen", "JCD.dH_int", 18);
			$this->EnableAction("Hardness_Washing");

			$this->RegisterVariableInteger("Hardness_Heater", "Szenen-Wasserhärte Heizung", "JCD.dH_int", 19);
			$this->EnableAction("Hardness_Heater");

			$this->RegisterVariableInteger("Hardness_Watering", "Szenen-Wasserhärte Bewässerung", "JCD.dH_int", 20);
			$this->EnableAction("Hardness_Watering");

			$this->RegisterVariableInteger("Hardness_Shower", "Szenen-Wasserhärte Duschen", "JCD.dH_int", 21);
			$this->EnableAction("Hardness_Shower");

			$this->RegisterVariableInteger("Hardness_Normal", "Szenen-Wasserhärte Normal", "JCD.dH_int", 22);
			$this->EnableAction("Hardness_Normal");

			$this->RegisterProfileInteger("JCD.Minutes", "Clock", "", " Minuten", 0, 1000, 1);
			$this->RegisterVariableInteger("remainingTime", "Restlaufzeit Szene", "JCD.Minutes", 23);

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
			$command = "none";
			$strSerialnumber = '&serialnumber=';

			switch($Ident) {
				case "Hardness_Washing":
					$command = "set%20waterscene%20washing";
					$parameter = (string) $Value;
					break;
				case "Hardness_Heater":
					$command = "set%20waterscene%20heaterfilling";
					$parameter = (string) $Value;
					break;
				case "Hardness_Watering":
					$command = "set%20waterscene%20watering";
					$parameter = (string) $Value;
					break;
				case "Hardness_Shower":
					$command = "set%20waterscene%20shower";
					$parameter = (string) $Value;
					break;
				case "Hardness_Normal":
					$command = "write%20data&dt=0x33&index=60&data=". $Value . "&da=0x1&&action=normal";
					$parameter = 0;
					break;
				case "activeScene":
					switch ($Value) {
						case 0:
							$action = "normal";
							$hardness = $this->GetValue('Hardness_Normal');
							$command = "write%20data&dt=0x33&index=201&data=" . $hardness . "&da=0x1&disable_time=" . "&action=" . $action;
							break;
						case 1:
							$action = "shower";
							$time = $this->ReadPropertyInteger("TimeShower");
							$hardness = $this->GetValue('Hardness_Shower');
							$command = "write%20data&dt=0x33&index=202&data=" . $hardness . "&da=0x1&disable_time=". (time() + $time*60) . "&action=" . $action;
							break;
						case 2:
							$action = "heaterfilling";
							$time = $this->ReadPropertyInteger("TimeHeating");
                            $hardness = $this->GetValue('Hardness_Heater');
							$command = "write%20data&dt=0x33&index=204&data=" . $hardness . "&da=0x1&disable_time=". (time() + $time*60) . "&action=" . $action;
							break;
						case 3:
							$action = "watering";
							$time = $this->ReadPropertyInteger("TimeWatering");
                            $hardness = $this->GetValue('Hardness_Watering');
							$command = "write%20data&dt=0x33&index=203&data=" . $hardness . "&da=0x1&disable_time=". (time() + $time*60) . "&action=" . $action;
							break;
						case 4:
							$action = "washing";
							$time = $this->ReadPropertyInteger("TimeWashing");
                            $hardness =  $this->GetValue('Hardness_Washing');
							$command = "write%20data&dt=0x33&index=205&data=" . $hardness . "&da=0x1&disable_time=". (time() + $time*60) . "&action=" . $action;
							break;
						
						default:
							break;
					}
					$strSerialnumber = '&serial_number=';
					$parameter = 0;
					break;

				default:
					throw new Exception("Invalid Ident");
			}

			if($command !== 'none'){
				$deviceCommandUrl = $url 
				. '/interface/?token=' . $this->ReadAttributeString("AccessToken") 
				. $strSerialnumber . $this->GetValue('deviceSN')
				. '&group=register&command=' 
				. $command . '&parameter=' . $parameter;

				$this->SendDebug(__FUNCTION__, 'Requesting API URL '. $deviceCommandUrl, 0);
				$response = $wc->Navigate($deviceCommandUrl);
				$json = json_decode($response, false);
				$this->SendDebug(__FUNCTION__, 'Received response from API: '. $response, 0);

				if(isset($json->status) && $json->status === 'ok')
				{
					$this->SetValue($Ident, $Value);
				}
				else{
					$this->SendDebug(__FUNCTION__, 'Error during request to JuControl API, ', 0);
				}
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
							$this->updateIfNecessary("Ja", "hasEmergencySupply");

                            $batteryValues = explode(':', $this->getInValue($deviceData, 93));

                            /* Battery percentage */
							if (isset($batteryValues[0])){
                                $this->updateIfNecessary((int) $batteryValues[0], "batteryState");
                            }

                            /* Battery runtime */
                            if (count($batteryValues) > 1){
                                $batteryRuntime = sprintf('%d:%02d:%02d', (int) $batteryValues[3], (int) $batteryValues[2],  (int) $batteryValues[1]);
                                $this->SendDebug(__FUNCTION__, 'TODO: BatteryRuntime (H:MM:SS) = ' . $batteryRuntime, 0);
                            }

                        }
						else{
							$this->updateIfNecessary("Nein", "hasEmergencySupply");
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
						$this->updateIfNecessary($sceneValue, "activeScene");
			

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
						$this->updateIfNecessary((int) $SaltLevelPercent, "rangeSaltPercent");
                        $this->SendDebug(__FUNCTION__, 'TODO: Salt Level (kg) = ' . $SaltLevel, 0);

                        $SaltRange = $saltInfo[1]; //Salzreichweite in Tagen
                        $this->SendDebug(__FUNCTION__, 'TODO: Salt Range (days) = ' . $SaltRange, 0);

                        /* Input hardness */
						$this->updateIfNecessary($this->getInValue($deviceData, 790, 26), "inputHardness");

						/* currentFlow */
						$this->updateIfNecessary($this->getInValue($deviceData, 790, 1617), "currentFlow");

						/* water stop */
                        $leckageschutzStatusflag = $this->getInValue($deviceData, 792, 0);
                        if (strlen($leckageschutzStatusflag) === 8) {
                            $wasserstop = $leckageschutzStatusflag[0];
                        } else {
                            $wasserstop = 0;
                        }
                        $this->SendDebug(__FUNCTION__, 'TODO: water_stop: ' . $wasserstop, 0);

                        /* read target hardness of waterscenes */
						$this->updateIfNecessary((int) $json->data[0]->hardness_washing, "Hardness_Washing");
						$this->updateIfNecessary((int) $json->data[0]->hardness_shower, "Hardness_Shower");
						$this->updateIfNecessary((int) $json->data[0]->hardness_watering, "Hardness_Watering");
						$this->updateIfNecessary((int) $json->data[0]->hardness_heater, "Hardness_Heater");
						$this->updateIfNecessary((int) $json->data[0]->waterscene_normal, "Hardness_Normal");

                        /* read times of waterscenes */
                        if (isset($json->data[0]->waterscene_time)){
                            $this->SendDebug(__FUNCTION__, 'TODO: waterscene_time (Stunden) = ' . $json->data[0]->waterscene_time, 0);
                        }
                        if (isset($json->data[0]->waterscene_time_garden)){
                            $this->SendDebug(__FUNCTION__, 'TODO: waterscene_time_garden (Stunden) = ' . $json->data[0]->waterscene_time_garden, 0);
                        }
                        if (isset($json->data[0]->waterscene_time_heater)){
                            $this->SendDebug(__FUNCTION__, 'TODO: waterscene_time_heater (Stunden) = ' . $json->data[0]->waterscene_time_heater, 0);
                        }
                        if (isset($json->data[0]->waterscene_time_washing)){
                            $this->SendDebug(__FUNCTION__, 'TODO: waterscene_time_washing (Stunden) = ' . $json->data[0]->waterscene_time_washing, 0);
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
						if($this->GetValue('activeScene') != 0)
						{
							if($json->data[0]->disable_time !== '')
							{
								$remainingTime = (((int)$json->data[0]->disable_time - time()) / 60) + 1;
								$this->updateIfNecessary((int) $remainingTime, "remainingTime");
								if ((int) $remainingTime <= 0)
								{
									$this->updateIfNecessary(0, "remainingTime");
								}
							}
							else
							{
								$this->updateIfNecessary(0, "remainingTime");
								$this->updateIfNecessary(0, "activeScene");
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

		private function updateIfNecessary($newValue, string $ident): void
		{
			if ($this->GetValue($ident) != $newValue)
			{
                $this->SetValue($ident, $newValue);
				$this->SendDebug(__FUNCTION__, 'Updating variable ' . $ident . ' to value: ' . $newValue, 0);
			}
		}
	}