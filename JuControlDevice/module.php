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
			$this->RegisterVariableString("targetHardness", "Ziel-Wasserhärte", "", 4);
			$this->RegisterVariableString("inputHardness", "Ist-Wasserhärte", "", 5);
			$this->RegisterVariableString("rangeSaltPercent", "Füllstand Salz", "", 6);
			$this->RegisterVariableString("currentFlow", "Aktueller Durchfluss", "", 7);
			$this->RegisterVariableString("batteryState", "Batteriezustand Notstrommodul", "", 8);
			$this->RegisterVariableString("activeScene", "Aktive Wasserszene", "", 9);
			$this->RegisterVariableString("swVersion", "SW Version", "", 10);
			$this->RegisterVariableString("hwVersion", "HW Version", "", 11);
			$this->RegisterVariableString("ccuVersion", "CCU Version", "", 12);
			$this->RegisterVariableString("nextService", "Nächste Wartung", "", 13);
			$this->RegisterVariableString("hasEmergencySupply", "Notstrommodul verbaut", "", 14);
			$this->RegisterVariableString("totalWater", "Gesamt-Durchfluss", "", 15);
			$this->RegisterVariableString("totalRegenaration", "Gesamt-Regenerationen", "", 16);
			$this->RegisterVariableString("totalService", "Gesamt-Wartungen", "", 17);

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


		public function RefreshData()
		{
			$wc = new WebClient();
			$url = 'https://www.myjudo.eu';
			$deviceDataUrl = $url . '/interface/?token=' . $this->ReadAttributeString("AccessToken") . '&group=register&command=get%20device%20data';
			$response = $wc->Navigate($deviceDataUrl);
	
			if ($response === FALSE) {
				die('Failed get device Data');
			}
			else {
				$json = json_decode($response);
				if(isset($json->status) && $json->status == 'ok')
				{
					/* Parse response */
					if ($json->data[0]->data[0]->dt == '0x33')
					{
						SetValue($this->GetIDForIdent("deviceType"), 'i-soft safe');
					}
					else
					{
						$this->SetStatus(202);
						IPS_LogMessage($this->InstanceID, 'Wrong device type found! -> Aborting!');
						$this->SetTimerInterval("RefreshTimer", 0);
					}
			
					/* Device S/N */
					SetValue($this->GetIDForIdent("deviceState"), $json->data[0]->serialnumber);
			
					/* Device state */
					SetValue($this->GetIDForIdent("deviceSN"), $json->data[0]->status);

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
	}