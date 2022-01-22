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
			$this->RegisterPropertyInteger("RefreshRate", 60 * 1000);
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

			$this->RegisterVariableString("totalRegenaration", "Gesamt-Regenerationen", "", 16);
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

			$this->SetStatus(104);

		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
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
			$deviceCommandUrl = $url;
			$command = "none";

			switch($Ident) {
				case "Hardness_Washing":
					$command = "set%20waterscene%20washing";
					$parameter = strval($Value);
					$deviceCommandUrl = $deviceCommandUrl
						. '/interface/' . '&command=' . $command
						. '?token=' .$this->ReadAttributeString("AccessToken") 
						. '&group=register' 
						. '&serial_number=' . GetValue($this->GetIDForIdent("deviceSN"))
						. '&parameter=' . $parameter;
					break;
				case "Hardness_Heater":
					$command = "set%20waterscene%20heaterfilling";
					$parameter = strval($Value);
					$deviceCommandUrl = $deviceCommandUrl
					. '/interface/' . '&command=' . $command
					. '?token=' .$this->ReadAttributeString("AccessToken") 
					. '&group=register' 
					. '&serial_number=' . GetValue($this->GetIDForIdent("deviceSN"))
					. '&parameter=' . $parameter;
					break;
				case "Hardness_Watering":
					$command = "set%20waterscene%20watering";
					$parameter = strval($Value);
					$deviceCommandUrl = $deviceCommandUrl
					. '/interface/' . '&command=' . $command
					. '?token=' .$this->ReadAttributeString("AccessToken") 
					. '&group=register' 
					. '&serial_number=' . GetValue($this->GetIDForIdent("deviceSN"))
					. '&parameter=' . $parameter;
					break;
				case "Hardness_Shower":
					$command = "set%20waterscene%20shower";
					$parameter = strval($Value);
					$deviceCommandUrl = $deviceCommandUrl
					. '/interface/' . '&command=' . $command
					. '?token=' .$this->ReadAttributeString("AccessToken") 
					. '&group=register' 
					. '&serial_number=' . GetValue($this->GetIDForIdent("deviceSN"))
					. '&parameter=' . $parameter;
					break;
				case "Hardness_Normal":
					$command = "write%20data&dt=0x33&index=60&data=". strval($Value) . "&da=0x1&&action=normal";
					$parameter = 0;
					$deviceCommandUrl = $deviceCommandUrl
					. '/interface/' . '&command=' . $command
					. '?token=' .$this->ReadAttributeString("AccessToken") 
					. '&group=register' 
					. '&serial_number=' . GetValue($this->GetIDForIdent("deviceSN"))
					. '&parameter=' . $parameter;
					break;
				case "activeScene":
					switch ($Value) {
						case 0:
							$action = "normal";
							$hardness = GetValue($this->GetIDForIdent("Hardness_Normal"));
							$command = "write%20data&dt=0x33&index=201&data=". strval($hardness) . "&da=0x1&disable_time=" ."&action=" . $action;
							break;
						case 1:
							$action = "shower";
							$time = $this->ReadPropertyInteger("TimeShower");
							$hardness = GetValue($this->GetIDForIdent("Hardness_Shower"));
							$command = "write%20data&dt=0x33&index=202&data=". strval($hardness) . "&da=0x1&disable_time=". strval(time() + $time*60) ."&action=" . $action;
							break;
						case 2:
							$action = "heaterfilling";
							$time = $this->ReadPropertyInteger("TimeHeating");
							$hardness = GetValue($this->GetIDForIdent("Hardness_Heater"));
							$command = "write%20data&dt=0x33&index=204&data=". strval($hardness) . "&da=0x1&disable_time=". strval(time() + $time*60) ."&action=" . $action;
							break;
						case 3:
							$action = "watering";
							$time = $this->ReadPropertyInteger("TimeWatering");
							$hardness = GetValue($this->GetIDForIdent("Hardness_Watering"));
							$command = "write%20data&dt=0x33&index=203&data=". strval($hardness) . "&da=0x1&disable_time=". strval(time() + $time*60) ."&action=" . $action;
							break;
						case 4:
							$action = "washing";
							$time = $this->ReadPropertyInteger("TimeWashing");
							$hardness = GetValue($this->GetIDForIdent("Hardness_Washing"));
							$command = "write%20data&dt=0x33&index=205&data=". strval($hardness) . "&da=0x1&disable_time=". strval(time() + $time*60) ."&action=" . $action;
							break;
						
						default:
							break;
					}
					$deviceCommandUrl = $url 
						. '/interface/?token=' . $this->ReadAttributeString("AccessToken") 
						. '&serial_number=' . GetValue($this->GetIDForIdent("deviceSN"))
						. '&group=register&command=' 
						. $command;
					break;

				default:
					throw new Exception("Invalid Ident");
			}

			if($command != "none"){

				$response = $wc->Navigate($deviceCommandUrl);
				$json = json_decode($response);

				if(isset($json->status) && $json->status == 'ok')
				{
					SetValue($this->GetIDForIdent($Ident), $Value);
				}
				else{
					$this->SendDebug('JuControlDevice:', 'Error during request to JuControl API: '. $deviceCommandUrl .' / response: ' . $response, 0);
				}
			}

			$this->RefreshData();

		 
		}

		public function RefreshData()
		{
			$wc = new WebClient();
			$url = 'https://www.myjudo.eu';
			$deviceDataUrl = $url . '/interface/?token=' . $this->ReadAttributeString("AccessToken") . '&group=register&command=get%20device%20data';
			$response = $wc->Navigate($deviceDataUrl);
	
			if ($response === FALSE) {
				//$this->SetStatus(104);
				//$this->SetTimerInterval("RefreshTimer", 0);
				$this->SendDebug('JuControlDevice:', 'Error during request to JuControl API: '. $deviceDataUrl, 0);
			}
			else {
				$json = json_decode($response);
				if(isset($json->status) && $json->status == 'ok')
				{
					/* Parse response */
					$this->SetStatus(102);
					if ($json->data[0]->data[0]->dt == '0x33')
					{
						$this->updateIfNecessary('i-soft safe', "deviceType");
					}
					else
					{
						$this->SetStatus(104);
						$this->SetStatus(202);
						$this->SendDebug('JuControlDevice received data:', 'Wrong device type: (' . $json->data[0]->data[0]->dt . ') found -> Aborting!', 0);
						$this->SetTimerInterval("RefreshTimer", 0);
					}
			
					/* Device S/N */
					$this->updateIfNecessary($json->data[0]->serialnumber, "deviceSN");
			
					/* Device state */
					$this->updateIfNecessary($json->data[0]->status, "deviceState");

					/* Connectivity module version */
					$this->updateIfNecessary($json->data[0]->sv, "ccuVersion");

					/* Emergency supply available */
					$emergencySupply = hexdec(substr(explode(':',$json->data[0]->data[0]->data->{790}->data)[1], 2, 2));
					if ($emergencySupply === 2 || $emergencySupply === 3)
					{
						$this->updateIfNecessary("Ja", "hasEmergencySupply");
						/* Battery percentage */
						$batteryPercentage = hexdec(substr($json->data[0]->data[0]->data->{93}->data, 6, 2));
						$this->updateIfNecessary($batteryPercentage, "batteryState");
					}
					else{
						$this->updateIfNecessary("Nein", "hasEmergencySupply");
					}

					
					

					/* Active scene */
					$sceneValue = -1;
					switch ($json->data[0]->waterscene) {
						case 'normal':
							$sceneValue = 0;
							break;
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
							$sceneValue = -1;
							break;
					}

					if($sceneValue != -1)
						$this->updateIfNecessary($sceneValue, "activeScene");
		


					/* HW Version */
					$hwMinor = intval(explode('.', $json->data[0]->data[0]->hv, 2)[1], 10);
					$hwMajor = explode('.', $json->data[0]->data[0]->hv, 2)[0];
			  
					if($hwMinor < 10)
					{
						$hwMinor = '0' . strval($hwMinor);
					}
					else
					{
						$hwMinor = strval($hwMinor);
					}
					$this->updateIfNecessary($hwMajor . '.' . $hwMinor, "hwVersion");
			
			
					/* SW Version */
					$swMinor = intval(explode('.', $json->data[0]->data[0]->sv, 2)[1], 10);
					$swMajor = explode('.', $json->data[0]->data[0]->sv, 2)[0];
			
					if($swMinor < 10)
					{
						$swMinor = '0' . strval($swMinor);
					}
					else
					{
						$swMinor = strval($swMinor);
					}
					$this->updateIfNecessary($swMajor . '.' . $swMinor, "swVersion");
			
					/* Device ID */
					$deviceIDhex = $this->formatEndian($json->data[0]->data[0]->data->{3}->data, 'N');
					$this->updateIfNecessary(hexdec($deviceIDhex), "deviceID");

					/* Total water*/
					$totalWaterHex = $this->formatEndian($json->data[0]->data[0]->data->{8}->data, 'N');
					$this->updateIfNecessary(hexdec($totalWaterHex), "totalWater");

					/* Next service */
					$hoursUntilNextService = hexdec($this->formatEndian(substr($json->data[0]->data[0]->data->{7}->data, 0, 4) . '0000', 'N'));
					$daysUntilNextService = $hoursUntilNextService / 24;
					$this->updateIfNecessary($daysUntilNextService, "nextService");

					/* Count regenaration */
					$countRegeneration = hexdec($this->formatEndian(substr(explode(':',$json->data[0]->data[0]->data->{791}->data)[1], 60, 4) . '0000', 'N'));
					$this->updateIfNecessary($countRegeneration, "totalRegenaration");

					/* Count service */
					$countService = hexdec($this->formatEndian(substr($json->data[0]->data[0]->data->{7}->data, 8, 4) . '0000', 'N'));
					$this->updateIfNecessary($countService, "totalService");

					/* Range Salt */
					$lowRangeSaltPercent = substr($json->data[0]->data[0]->data->{94}->data, 0, 2);
					$highRangeSaltPercent = substr($json->data[0]->data[0]->data->{94}->data, 2, 2);
					$rangeSaltPercent = 2 * (hexdec($highRangeSaltPercent . $lowRangeSaltPercent) / 1000);		
					$this->updateIfNecessary(intval($rangeSaltPercent), "rangeSaltPercent");

					/* Input /target hardness */
					$inputHardness = hexdec(substr(explode(':',$json->data[0]->data[0]->data->{790}->data)[1], 52, 2));
					$targetHardness = hexdec(substr(explode(':',$json->data[0]->data[0]->data->{790}->data)[1], 16, 2));
					$this->updateIfNecessary($inputHardness, "inputHardness");
					$this->updateIfNecessary($targetHardness, "targetHardness");


					/* currentFlow */
					$lowCurrentFlow = substr(explode(':', $json->data[0]->data[0]->data->{790}->data)[1], 32, 2);
					$highCurrentFlow = substr(explode(':', $json->data[0]->data[0]->data->{790}->data)[1], 34, 2);
					$currentFlow = hexdec($highCurrentFlow . $lowCurrentFlow);
					$this->updateIfNecessary($currentFlow, "currentFlow");

					/* read target hardness of waterscenes */

					$this->updateIfNecessary(intval($json->data[0]->hardness_washing), "Hardness_Washing");
					$this->updateIfNecessary(intval($json->data[0]->hardness_shower), "Hardness_Shower");
					$this->updateIfNecessary(intval($json->data[0]->hardness_watering), "Hardness_Watering");
					$this->updateIfNecessary(intval($json->data[0]->hardness_heater), "Hardness_Heater");
					$this->updateIfNecessary(intval($json->data[0]->waterscene_normal), "Hardness_Normal");

					/* check waterscene and update target hardness */
					switch (GetValue($this->GetIDForIdent("activeScene"))) {
						case '4':
							$this->updateIfNecessary(intval($json->data[0]->hardness_washing), "targetHardness");
							break;
						case '1':
							$this->updateIfNecessary(intval($json->data[0]->hardness_shower), "targetHardness");
							break;
						case '3':
							$this->updateIfNecessary(intval($json->data[0]->hardness_watering), "targetHardness");
							break;
						case '2':
							$this->updateIfNecessary(intval($json->data[0]->hardness_heater), "targetHardness");
							break;
						case '0':							
						default:
							/* do not update */
							break;
					}

					/* Remaining time of active water scene */
					if(GetValue($this->GetIDForIdent("activeScene")) != 0)
					{
						if($json->data[0]->disable_time != '')
						{
							$remainingTime = (intval($json->data[0]->disable_time) - time()) / 60 ;
							$this->updateIfNecessary(intval($remainingTime), "remainingTime");
							if (intval($remainingTime) <= 0)
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
					/* Token not valid -> try to login again one time and wait for next RefreshData! */
					$this->Login();
				}
				
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
		
			$this->SendDebug('JuControlDevice:', 'Trying to login with username: '. $username, 0);


			$response = $wc->Navigate($loginUrl);
			if ($response === FALSE) 
			{
				$this->SetStatus(201);
			}
			else 
			{
				$json = json_decode($response);
				if (isset($json->status) && $json->status == 'ok')
				{
					$this->SendDebug('JuControlDevice:', 'Login successful, Token: '. $json->token, 0);
					$this->WriteAttributeString("AccessToken", $json->token);
					$this->SetStatus(102);
					
					$refreshRate = $this->ReadPropertyInteger("RefreshRate");
					$this->SetTimerInterval("RefreshTimer", $refreshRate * 1000);
				}
				else
				{
					$this->SendDebug('JuControlDevice:', 'Login failed!', 0);
					$this->SetStatus(201);
					$this->SetTimerInterval("RefreshTimer", 0);
				}
			}
		
		}

		public function TestConnection()
		{
			$this->Login();
		}

		function formatEndian($endian, $format = 'N') {
			$endian = intval($endian, 16);      // convert string to hex
			$endian = pack('L', $endian);       // pack hex to binary sting (unsinged long, machine byte order)
			$endian = unpack($format, $endian); // convert binary sting to specified endian format
		
			return sprintf("%'.08x", $endian[1]); // return endian as a hex string (with padding zero)
		}

		private function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
		{
				if (!IPS_VariableProfileExists($Name))
				{
					IPS_CreateVariableProfile($Name, 1);
				}
				else
				{
					$profile = IPS_GetVariableProfile($Name);
					if ($profile['ProfileType'] != 1)
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
					IPS_CreateVariableProfile($Name, 2);
				}
				else
				{
					$profile = IPS_GetVariableProfile($Name);
					if ($profile['ProfileType'] != 2)
						throw new Exception("Variable profile type does not match for profile " . $Name);
				}
				IPS_SetVariableProfileIcon($Name, $Icon);
				IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
				IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);        
		}

		private function updateIfNecessary($newValue, $ident)
		{
			$id = $this->GetIDForIdent($ident);
			if(GetValue($id) != $newValue)
			{
				SetValue($id, $newValue);
				$this->SendDebug('JuControlDevice:', 'Updating variable ' . IPS_GetName($id) . ' to value: ' . $newValue, 0);
			}
				
		}
	}