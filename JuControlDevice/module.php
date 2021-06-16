<?php

declare(strict_types=1);
require_once('Webclient.php');

	class JuControlDevice extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterAttributeString("AccessToken", "noToken");

			$this->RegisterTimer("RefreshTimer", 0, 'JCD_RefreshData('. $this->InstanceID . ');');	

			$this->RegisterPropertyString("Username", "");
			$this->RegisterPropertyString("Passwort", "");

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


			$this->RegisterVariableString("activeScene", "Aktive Wasserszene", "", 9);


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
		}

		/* wird aufgerufen, wenn eine Variable geändert wird */
		public function RequestAction($Ident, $Value) {
 
			switch($Ident) {
				case "TestVariable":
					//Hier würde normalerweise eine Aktion z.B. das Schalten ausgeführt werden
					//Ausgaben über 'echo' werden an die Visualisierung zurückgeleitet
		 
					//Neuen Wert in die Statusvariable schreiben
					SetValue($this->GetIDForIdent($Ident), $Value);
					break;
				default:
					throw new Exception("Invalid Ident");
			}
		 
		}

		public function RefreshData()
		{
			$wc = new WebClient();
			$url = 'https://www.myjudo.eu';
			$deviceDataUrl = $url . '/interface/?token=' . $this->ReadAttributeString("AccessToken") . '&group=register&command=get%20device%20data';
			$response = $wc->Navigate($deviceDataUrl);
	
			if ($response === FALSE) {
				$this->SetStatus(104);
				$this->SetTimerInterval("RefreshTimer", 0);
			}
			else {
				$json = json_decode($response);
				if(isset($json->status) && $json->status == 'ok')
				{
					/* Parse response */
					$this->SetStatus(102);
					if ($json->data[0]->data[0]->dt == '0x33')
					{
						SetValue($this->GetIDForIdent("deviceType"), 'i-soft safe');
					}
					else
					{
						$this->SetStatus(104);
						$this->SetStatus(202);
						IPS_LogMessage($this->InstanceID, 'Wrong device type found! -> Aborting!');
						$this->SetTimerInterval("RefreshTimer", 0);
					}
			
					/* Device S/N */
					SetValue($this->GetIDForIdent("deviceSN"), $json->data[0]->serialnumber);
			
					/* Device state */
					SetValue($this->GetIDForIdent("deviceState"), $json->data[0]->status);

					/* Connectivity module version */
					SetValue($this->GetIDForIdent("ccuVersion"), $json->data[0]->sv);

					/* Active scene */
					SetValue($this->GetIDForIdent("activeScene"), $json->data[0]->waterscene);

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
			  
					SetValue($this->GetIDForIdent("hwVersion"), $hwMajor . '.' . $hwMinor);
			
			
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
			
					SetValue($this->GetIDForIdent("swVersion"), $swMajor . '.' . $swMinor);
			
					/* Device ID */
					$deviceIDhex = $this->formatEndian($json->data[0]->data[0]->data->{3}->data, 'N');
					SetValue($this->GetIDForIdent("deviceID"), hexdec($deviceIDhex));

					/* Total water*/
					$totalWaterHex = $this->formatEndian($json->data[0]->data[0]->data->{8}->data, 'N');
					SetValue($this->GetIDForIdent("totalWater"), hexdec($totalWaterHex));

					/* Next service */
					$hoursUntilNextService = hexdec($this->formatEndian(substr($json->data[0]->data[0]->data->{7}->data, 0, 4) . '0000', 'N'));
					$daysUntilNextService = $hoursUntilNextService / 24;
					SetValue($this->GetIDForIdent("nextService"), $daysUntilNextService);

					/* Count regenaration */
					$countRegeneration = hexdec($this->formatEndian(substr(explode(':',$json->data[0]->data[0]->data->{791}->data)[1], 60, 4) . '0000', 'N'));
					echo substr(explode(':',$json->data[0]->data[0]->data->{791}->data)[1], 60, 4) . '0000';
					SetValue($this->GetIDForIdent("totalRegenaration"), $countRegeneration);

					/* Count service */
					$countService = hexdec($this->formatEndian(substr($json->data[0]->data[0]->data->{7}->data, 8, 4) . '0000', 'N'));
					SetValue($this->GetIDForIdent("totalService"), $countService);

					/* Range Salt */
					$lowRangeSaltPercent = substr($json->data[0]->data[0]->data->{94}->data, 4, 2);
					$highRangeSaltPercent = substr($json->data[0]->data[0]->data->{94}->data, 4, 2);
					$rangeSaltPercent = hexdec($highRangeSaltPercent + $lowRangeSaltPercent);

					SetValue($this->GetIDForIdent("rangeSaltPercent"), $rangeSaltPercent);

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
			$passwd = $this->ReadPropertyString("Passwort");

			$loginUrl = $url . '/interface/?group=register&command=login&name=login&user=' . $username . '&password=' . md5($passwd, false) . '&nohash=' . $passwd . '&role=customer';
		
			IPS_LogMessage($this->InstanceID, 'Trying to login with username: '. $username);

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
					IPS_LogMessage($this->InstanceID, 'Login successful, Token: '. $json->token);
					$this->WriteAttributeString("AccessToken", $json->token);
					$this->SetStatus(102);
					$this->SetTimerInterval("RefreshTimer", 60 * 1000);
				}
				else
				{
					IPS_LogMessage($this->InstanceID, 'Login failed!');
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
	}