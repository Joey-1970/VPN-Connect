<?
    // Klassendefinition
    class VPNConnect extends IPSModule 
    {
	// Überschreibt die interne IPS_Create($id) Funktion
        public function Create() 
        {
            	// Diese Zeile nicht löschen.
            	parent::Create();
		$this->RegisterMessage(0, IPS_KERNELSTARTED);
		
            	$this->RegisterPropertyBoolean("Open", false);
		$this->RegisterPropertyString("IPAddress", "127.0.0.1");
		
		$this->RegisterPropertyString("Gateway", "xxxx.myfritz.net");
		$this->RegisterPropertyString("ID", "VPN");
		$this->RegisterPropertyString("Secret", "xxxx");
		$this->RegisterPropertyString("AuthMode", "psk");
		$this->RegisterPropertyString("Username", "User");
		$this->RegisterPropertyString("Password", "Passwort");
		$this->RegisterPropertyInteger("LocalPort", 0);
		$this->RegisterPropertyInteger("DPDidle", 0);
				
		$this->RegisterPropertyBoolean("StartVPNwithIPS", false);
		$this->RegisterPropertyBoolean("VPNAutoRestart", false);
		$this->RegisterPropertyInteger("MaxWaitTime", 100);
		$this->RegisterPropertyInteger("Tries", 5);
		$this->RegisterPropertyInteger("TimerConnectionTest", 3);
		$this->RegisterTimer("ConnectionTest", 0, 'VPNConnect_GetDataUpdate($_IPS["TARGET"]);');
		
		// Profile anlegen
		$this->RegisterProfileInteger("VPNConnect.State", "Information", "", "", 0, 3, 1);
		IPS_SetVariableProfileAssociation("VPNConnect.State", 0, "Unbekannt", "Information", -1);
		IPS_SetVariableProfileAssociation("VPNConnect.State", 1, "Offline", "Close", 0xFF0000);
		IPS_SetVariableProfileAssociation("VPNConnect.State", 2, "Störung", "Alert", 0xFFFF00);
		IPS_SetVariableProfileAssociation("VPNConnect.State", 3, "Online", "Network", 0x00FF00);
		
		$this->RegisterProfileFloat("VPNConnect.ms", "Clock", "", " ms", 0, 1000, 0.001, 3);
		
		// Status-Variablen anlegen
		$this->RegisterVariableInteger("LastUpdate", "Letztes Update", "~UnixTimestamp", 10);
		$this->RegisterVariableInteger("State", "Status", "VPNConnect.State", 40);
		$this->RegisterVariableInteger("SuccessRate", "Erfolgsqoute", "~Intensity.100", 60);
		$this->RegisterVariableFloat("MinDuration", "Minimale Dauer", "VPNConnect.ms", 70);
		$this->RegisterVariableFloat("AvgDuration", "Durchschnittliche Dauer", "VPNConnect.ms", 80);
		$this->RegisterVariableFloat("MaxDuration", "Maximale Dauer", "VPNConnect.ms", 90);
		$this->RegisterVariableBoolean("StartVPNwithIPS", "VPN mit IP-Symcon starten", "~Switch", 100);
		$this->RegisterVariableBoolean("VPNAutoRestart", "VPN Restart Automatik", "~Switch", 110);
		$this->RegisterVariableBoolean("VPNActive", "VPN aktivieren", "~Switch", 120);
		$this->EnableAction("VPNActive");
		$this->RegisterVariableString("VPNFeedback", "VPN Rückmeldung", "", 130);
		

        }
       	
	public function GetConfigurationForm() { 
		$arrayStatus = array(); 
		$arrayStatus[] = array("code" => 101, "icon" => "inactive", "caption" => "Instanz wird erstellt"); 
		$arrayStatus[] = array("code" => 102, "icon" => "active", "caption" => "Instanz ist aktiv");
		$arrayStatus[] = array("code" => 104, "icon" => "inactive", "caption" => "Instanz ist inaktiv");
		$arrayStatus[] = array("code" => 200, "icon" => "error", "caption" => "Instanz ist fehlerhaft"); 
		$arrayStatus[] = array("code" => 202, "icon" => "error", "caption" => "Kommunikationfehler!");
		
		$arrayElements = array(); 
		$arrayElements[] = array("name" => "Open", "type" => "CheckBox", "caption" => "Aktiv"); 
				
		$arrayElements[] = array("type" => "Label", "caption" => "_____________________________________________________________________________________________________"); 
		
		$arrayElements[] = array("type" => "ValidationTextBox", "name" => "Gateway", "caption" => "Serveradresse / Server");
		$arrayElements[] = array("type" => "ValidationTextBox", "name" => "ID", "caption" => "IPSec-ID / Gruppenname");
		$arrayElements[] = array("type" => "ValidationTextBox", "name" => "Secret", "caption" => "IPSec-Schlüssel / Shared Secret");
		$arrayElements[] = array("type" => "ValidationTextBox", "name" => "AuthMode", "caption" => "Authentifizierungs-Mode (Default: psk)");
		$arrayElements[] = array("type" => "ValidationTextBox", "name" => "Username", "caption" => "Nutzername / Account");
		$arrayElements[] = array("type" => "PasswordTextBox", "name" => "Password", "caption" => "Passwort (Kennwort des FRITZ!Box-Benutzers von Nutzername / Account)");
		$arrayElements[] = array("type" => "NumberSpinner", "name" => "LocalPort", "caption" => "Lokaler Port", "minimum" => 0, "maximum" => 65535, "suffix" => "Port");
		$arrayElements[] = array("type" => "NumberSpinner", "name" => "DPDidle", "caption" => "Sende DPD wenn unbenutzt für x Sekunden (Default 0)", "minimum" => 0, "maximum" => 86400, "suffix" => "sek");

		$arrayElements[] = array("type" => "Label", "caption" => "_____________________________________________________________________________________________________"); 
		
		$arrayElements[] = array("type" => "ValidationTextBox", "name" => "IPAddress", "caption" => "IP die zum Test im VPN-Zielnetz angepingt werden soll");
		$arrayElements[] = array("type" => "NumberSpinner", "name" => "TimerConnectionTest", "caption" => "Wiederholung des Anpingen (1 - 15)", "minimum" => 1, "maximum" => 15, "suffix" => "min");
		$arrayElements[] = array("type" => "NumberSpinner", "name" => "MaxWaitTime", "caption" => "Maximale Wartezeit Ping (50 - 1000)", "minimum" => 50, "maximum" => 1000, "suffix" => "ms");
		$arrayElements[] = array("type" => "NumberSpinner", "name" => "Tries", "caption" => "Versuche (2 - 15)", "minimum" => 2, "maximum" => 15, "suffix" => "Anzahl");
		
		$arrayElements[] = array("name" => "StartVPNwithIPS", "type" => "CheckBox", "caption" => "VPN mit IP-Symcon starten"); 
		$arrayElements[] = array("name" => "VPNAutoRestart", "type" => "CheckBox", "caption" => "VPN Restart Automatik"); 
		
		$arrayElements[] = array("type" => "Label", "caption" => "_____________________________________________________________________________________________________"); 
	
		$arrayActions = array(); 
		$arrayActions[] = array("type" => "Label", "label" => "Test Center"); 
		$arrayActions[] = array("type" => "TestCenter", "name" => "TestCenter");
		
 		return JSON_encode(array("status" => $arrayStatus, "elements" => $arrayElements, "actions" => $arrayActions)); 		 
 	} 
	
	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() 
        {
                // Diese Zeile nicht löschen
                parent::ApplyChanges();
		
		If ($this->ReadPropertyBoolean("StartVPNwithIPS") <> $this->GetValue("StartVPNwithIPS")) {
			$this->SetValue("StartVPNwithIPS", $this->ReadPropertyBoolean("StartVPNwithIPS"));
		}
		
		If ($this->ReadPropertyBoolean("VPNAutoRestart") <> $this->GetValue("VPNAutoRestart")) {
			$this->SetValue("VPNAutoRestart", $this->ReadPropertyBoolean("VPNAutoRestart"));
		}
			
		If ($this->ReadPropertyBoolean("Open") == true) {
			If ($this->GetStatus() <> 102) {
				$this->SetStatus(102);
			}
			$this->GetDataUpdate();
			$this->SetTimerInterval("ConnectionTest", $this->ReadPropertyInteger("TimerConnectionTest") * 60 * 1000);
			If ((IPS_GetKernelRunlevel() == KR_READY) AND ($this->ReadPropertyBoolean("StartVPNwithIPS") == true)) {
				$this->StartVPN();
			}
		}
		else {
			If ($this->GetStatus() <> 104) {
				$this->SetStatus(104);
			}
			$this->SetTimerInterval("ConnectionTest", 0);
		}	   
	}
	    
	public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    	{
 		switch ($Message) {
			case IPS_KERNELSTARTED:
				If ($this->ReadPropertyBoolean("StartVPNwithIPS") == true) {
					$this->StartVPN();
				}
				break;
			
		}
    	}      
	
	public function RequestAction($Ident, $Value) 
	{
  		switch($Ident) {
			case "VPNActive":
				If ($Value == true) {
					$this->StartVPN();
				}
				else {
					$this->StopVPN();
				}
				break;
			
	      		
	        default:
	            throw new Exception("Invalid Ident");
	    	}
	}
	    
	public function GetDataUpdate()
	{
		
		$Result = unserialize($this->Multiple_Ping());
		$Ping = $Result["Ping"];
		$SuccessRate = $Result["SuccessRate"];
		$MinDuration = $Result["MinDuration"];
		$AvgDuration = $Result["AvgDuration"];
		$MaxDuration = $Result["MaxDuration"];
		
	
		If ($Ping <> $this->GetValue("State")) {
			
			//$SentDisorder = $this->ReadPropertyBoolean("SentDisorder");
			If ($Ping == 1) { // offline
				//$this->Notification($this->ReadPropertyString("TextDown"));
				If (($this->ReadPropertyBoolean("Open") == true) AND ($this->ReadPropertyBoolean("VPNAutoRestart") == true) AND $this->GetValue("VPNActive", true)) {
					$this->StartVPN();
				}
			} elseif ($Ping == 2) { // gestört
				/*
				If ($SentDisorder == true) {
					$this->Notification($this->ReadPropertyString("TextDisorder"));
				}
				*/
			} elseif ($Ping == 3) { // online
				/*
				If ($SentDisorder == true) {
					$this->Notification($this->ReadPropertyString("TextUp"));
				}
				elseif (($SentDisorder == false) AND (GetValueInteger($this->GetIDForIdent("State")) <> 2)) {
					$this->Notification($this->ReadPropertyString("TextUp"));
				}
				*/
			}
			$this->SetValue("State", $Ping);
		}
	
		If ($SuccessRate <> $this->GetValue("SuccessRate")) {
			$this->SetValue("SuccessRate", $SuccessRate);
		}
		If ($MinDuration <> $this->GetValue("MinDuration")) {
			$this->SetValue("MinDuration", $MinDuration);
		}
		If ($AvgDuration <> $this->GetValue("AvgDuration")) {
			$this->SetValue("AvgDuration", $AvgDuration);
		}
		If ($MaxDuration <> $this->GetValue("MaxDuration")) {
			$this->SetValue("MaxDuration", $MaxDuration);
		}
		$this->SetValue("LastUpdate", time() );
		
	}
	
	private function Multiple_Ping()
	{
    		$this->SendDebug("Multiple_Ping", "Ausfuehrung", 0);
		$IP = $this->ReadPropertyString("IPAddress");
		$MaxWaitTime = $this->ReadPropertyInteger("MaxWaitTime");
		$MaxWaitTime = min(1000, max(50, $MaxWaitTime));
		$Tries = $this->ReadPropertyInteger("Tries");
		$Tries = min(15, max(2, $Tries));
		$Result = array();
		$Ping = array();
		$Duration = array();
		
		for ($i = 0; $i < $Tries; $i++) {
			$Start = microtime(true);
			$Response = Sys_Ping($IP, $MaxWaitTime); 
			$Duration[] = microtime(true) - $Start;
			$Ping[] = $Response;
			
		}
		// Ping-Werte berechnen
		$MinDuration = round(min($Duration) * 1000, 2);
		$AvgDuration = round((array_sum($Duration)/count($Duration)) * 1000, 2);
		$MaxDuration = round(max($Duration) * 1000, 2);
		// Erfolg auswerten
		$SuccessRate = Round((array_sum($Ping)/count($Ping)) * 100, 2);
		$this->SendDebug("Multiple_Ping", "Min: ".$MinDuration."ms, Durchschnitt: ".$AvgDuration."ms, Max: ".$MaxDuration."ms, Erfolg: ".$SuccessRate."%"." Versuche: ".(count($Ping)), 0);
		If ($SuccessRate == 100) {
			$Result["Ping"] = 3;
		}
		elseif ($SuccessRate == 0) {
			$Result["Ping"] = 1;
		}
		else {
			$Result["Ping"] = 2;
		}
		$Result["SuccessRate"] = $SuccessRate;
		$Result["MinDuration"] = $MinDuration;
		$Result["AvgDuration"] = $AvgDuration;
		$Result["MaxDuration"] = $MaxDuration;
		
		$this->CheckVPNState();
		
	return serialize($Result);
	}   
	 
	public function CheckVPNState()
	{
		$Message = "ps aux |grep vpnc|grep -v grep|awk '{print $2}'"; 
		$Response = shell_exec($Message);
		$this->SendDebug("CheckVPNState", "Rueckmeldung: ".$Response, 0);
		$Response = trim($Response, "\x00..\x1F");	
		$MessageParts = explode(PHP_EOL, $Response);
		$this->SendDebug("CheckVPNState", "Anzahl: ".count($MessageParts), 0);
		If (count($MessageParts) == 1) {
			$this->SendDebug("CheckVPNState", "Rueckmeldung: VPNC laeuft, keine Verbindung", 0);
		} elseIf (count($MessageParts) == 2) {
			$this->SendDebug("CheckVPNState", "Rueckmeldung: VPNC laeuft, Verbindung besteht", 0);
		} else {
			$this->SendDebug("CheckVPNState", "Rueckmeldung: ist: ".count($MessageParts), 0);
		}
	}
	    
	public function StartVPN()
	{
		If ($this->ReadPropertyBoolean("Open") == true) {
			$Gateway = $this->ReadPropertyString("Gateway");
			$ID = $this->ReadPropertyString("ID");
			$Secret = $this->ReadPropertyString("Secret");
			$AuthMode = $this->ReadPropertyString("AuthMode");
			$Username = $this->ReadPropertyString("Username");
			$Password = $this->ReadPropertyString("Password");
			$LocalPort = $this->ReadPropertyInteger("LocalPort");
			$DPDidle = $this->ReadPropertyInteger("DPDidle");
		
			
			$this->SendDebug("StartVPN", "Ausfuehrung", 0);
			// zur Sicherheit einmal schließen
			$Message = 'sudo vpnc-disconnect'; 
			$Response = shell_exec($Message);
			If ($Response <> $this->GetValue("VPNFeedback")) {
				$this->SetValue("VPNFeedback", $Response);
			}
			$this->SendDebug("StartVPN", "Rueckmeldung: ".$Response, 0);
			// jetzt starten		
			$Message = 'sudo vpnc --gateway '.$Gateway.' --id '.$ID.' --secret '.$Secret.' --auth-mode '.$AuthMode.' --username '.$Username.' --password '.$Password.' --local-port '.$LocalPort.' --dpd-idle '.$DPDidle; 
			$Response = shell_exec($Message);
			If ($Response <> $this->GetValue("VPNFeedback")) {
				$this->SetValue("VPNFeedback", $Response);
			}
			$this->SendDebug("StartVPN", "Rueckmeldung: ".$Response, 0);
			$this->SetValue("VPNActive", true);
			
			$this->GetDataUpdate();
		}
	}
	    
	public function StopVPN()
	{
		If ($this->ReadPropertyBoolean("Open") == true) {
			$this->SendDebug("StopVPN", "Ausfuehrung", 0);
			$Message = 'sudo vpnc-disconnect'; 
			$Response = shell_exec($Message);
			If ($Response <> $this->GetValue("VPNFeedback")) {
				$this->SetValue("VPNFeedback", $Response);
			}
			$this->SendDebug("StopVPN", "Rueckmeldung: ".$Response, 0);
			$this->SetValue("VPNActive", false);
			
			$this->GetDataUpdate();
		}
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
	    
	private function RegisterProfileFloat($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits)
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
	        IPS_SetVariableProfileDigits($Name, $Digits);
	}


}
?>
