<?
    // Klassendefinition
    class VPNConnect extends IPSModule 
    {
	 // https://mpd.readthedocs.io/en/stable/protocol.html#command-reference
	    
	// Überschreibt die interne IPS_Create($id) Funktion
        public function Create() 
        {
            	// Diese Zeile nicht löschen.
            	parent::Create();
            	$this->RegisterPropertyBoolean("Open", false);
		$this->RegisterPropertyString("IPAddress", "127.0.0.1");
		$this->RegisterPropertyInteger("Port", 6600);
		
		$this->RegisterTimer("ConnectionTest", 0, 'VPNConnect_ConnectionTest($_IPS["TARGET"]);');
		
		// Profile anlegen
		
		
		

        }
       	
	public function GetConfigurationForm() { 
		$arrayStatus = array(); 
		$arrayStatus[] = array("code" => 101, "icon" => "inactive", "caption" => "Instanz wird erstellt"); 
		$arrayStatus[] = array("code" => 102, "icon" => "active", "caption" => "Instanz ist aktiv");
		$arrayStatus[] = array("code" => 104, "icon" => "inactive", "caption" => "Instanz ist inaktiv");
		$arrayStatus[] = array("code" => 200, "icon" => "error", "caption" => "Instanz ist fehlerhaft"); 
		$arrayStatus[] = array("code" => 202, "icon" => "error", "caption" => "Kommunikationfehler!");
		
		$arrayElements = array(); 
		$arrayElements[] = array("name" => "Open", "type" => "CheckBox",  "caption" => "Aktiv"); 
		$arrayElements[] = array("type" => "ValidationTextBox", "name" => "IPAddress", "caption" => "IP");
		$arrayElements[] = array("type" => "NumberSpinner", "name" => "Port", "caption" => "Port (1 - 65535)", "minimum" => 1, "maximum" => 65535);
		
		
				
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
		
		
			
			If ($this->ReadPropertyBoolean("Open") == true) {
				
					If ($this->GetStatus() <> 102) {
						$this->SetStatus(102);
					}
				
					$this->SetTimerInterval("ConnectionTest", 3 * 60 * 1000);
				}
			}
			else {
				If ($this->GetStatus() <> 104) {
					$this->SetStatus(104);
				}
				$this->SetTimerInterval("ConnectionTest", 0);
			}	   
		}
		
		
		
	}
	
	public function RequestAction($Ident, $Value) 
	{
  		switch($Ident) {
			case "Volume":
				$this->SetVolume($Value);
				break;
			
	      		
	        default:
	            throw new Exception("Invalid Ident");
	    	}
	}
	    
	
	
	
	    
	
	    
	public function ConnectionTest()
	{
	      	$result = false;
		return $result;
		
		
		$IPAddress = $this->ReadPropertyString("IPAddress");
		$Port = $this->ReadPropertyInteger("Port");
	      	If (Sys_Ping($IPAddress, 300)) {
			$status = @fsockopen($IPAddress, $Port, $errno, $errstr, 10);
			if (!$status) {
				$this->SendDebug("ConnectionTest", "Port ".$Port." ist geschlossen!", 0);
				IPS_LogMessage("MusicPlayerDaemon","Port ".$Port." ist geschlossen!");
				If ($this->GetStatus() <> 202) {
					$this->SetStatus(202);
				}
			}
		      	else {
				$result = true;
				If ($this->GetStatus() <> 102) {
					$this->SetStatus(102);
				}
			}
		}
		else {
			$this->SendDebug("ConnectionTest", "IP ".$IPAddress." reagiert nicht!", 0);
			IPS_LogMessage("MusicPlayerDaemon","IP ".$IPAddress." reagiert nicht!");
			If ($this->GetStatus() <> 202) {
				$this->SetStatus(202);
			}
		}
	return $result;
	}
	
	


}
?>
