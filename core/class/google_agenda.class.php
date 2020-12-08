<?php

  /* This file is part of Jeedom.
	*
	* Jeedom is free software: you can redistribute it and/or modify
	* it under the terms of the GNU General Public License as published by
	* the Free Software Foundation, eiher version 3 of the License, or
	* (at your option) any later version.
	*
	* Jeedom is distributed in the hope that it will be useful,
	* but WITHOUT ANY WARRANTY; without even the implied warranty of
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	* GNU General Public License for more details.
	*
	* You should have received a copy of the GNU General Public License
	* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
	*/

  /* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/../../vendor/autoload.php';

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class google_agenda extends eqLogic {
  /*     * *************************Attributs****************************** */

	public static function cron() {
		log::add('google_agenda', 'debug', "********************************************************************");
		log::add('google_agenda', 'debug', "Fonction : " . __FUNCTION__);
		foreach (self::byType('google_agenda') as $eqLogic) {
			try {
				if ($eqLogic->getConfiguration("type_equipement") =="filtre"){
					log::add('google_agenda', 'debug', "Nom équipement: " . $eqLogic->getHumanName());
					$eqLogic->recup_filtre();
					log::add('google_agenda', 'debug', "--------------------------------------------------------------------");
				}
			} catch (Exception $e) {
				log::add('google_agenda', 'warning', __('Erreur sur : ', __FILE__) . $eqLogic->getHumanName() . ' => ' . $e->getMessage());
			}
		}
	}
	public static function cron5() {
		log::add('google_agenda', 'debug', "********************************************************************");
		log::add('google_agenda', 'debug', "Fonction : " . __FUNCTION__);
		foreach (self::byType('google_agenda') as $eqLogic) {
			try {
				if ($eqLogic->getConfiguration("type_equipement") =="agenda"){
					log::add('google_agenda', 'debug', "Nom équipement: " . $eqLogic->getHumanName());
					$eqLogic->Synchronisation_google();
					log::add('google_agenda', 'debug', "--------------------------------------------------------------------");
				}
			} catch (Exception $e) {
				log::add('google_agenda', 'warning', __('Erreur sur : ', __FILE__) . $eqLogic->getHumanName() . ' => ' . $e->getMessage());
			}
		}
	}
  /*     * ***********************Methode static*************************** */
	public function ping() {
		$ping = "NOK";
		$exec_string = 'sudo ping -n -c 1 -t 255 8.8.8.8';
		exec($exec_string, $output, $return);
		$output = array_values(array_filter($output));
		
		
		log::add('google_agenda', 'error',  $output);
		if (!empty($output[1])) {
			if (count($output) >= 5) {
				$response = preg_match("/time(?:=|<)(?<time>[\.0-9]+)(?:|\s)ms/", $output[count($output)-4], $matches);
				if ($response > 0 && isset($matches['time'])) {
					$ping = "OK";
				}				
			}			
		}	
		return $ping;
}
	public function getProvider() {
		return new googleProvider([
			'clientId' => $this->getConfiguration('client_id'),
			'clientSecret' => $this->getConfiguration('client_secret'),
			'redirectUri' => network::getNetworkAccess('external') . '/plugins/google_agenda/core/php/callback.php?apikey=' . jeedom::getApiKey('google_agenda') . '&eqLogic_id=' . $this->getId(),
			'accessType' => 'offline',
		]);
	}

	public function getAccessToken($_forceRefresh = false) {
		$provider = $this->getProvider();
		$existingAccessToken = new AccessToken($this->getConfiguration('accessToken'));
		if ($existingAccessToken->hasExpired() || $_forceRefresh) {
			$newAccessToken = $provider->getAccessToken('refresh_token', [
				'refresh_token' => $this->getConfiguration('refreshToken'),
			]);
			$this->setConfiguration('accessToken', $newAccessToken->jsonSerialize());
			$this->save();
			return $newAccessToken;
		}
		return $existingAccessToken;
	}

	public function linkToUser() {
		@session_start();
		$provider = $this->getProvider();
		$authorizationUrl = $provider->getAuthorizationUrl(['approval_prompt' => 'force']);
		$_SESSION['oauth2state'] = $provider->getState();
		return $authorizationUrl;
	}

	public function request($_type, $_request, $_options = array()) {
		
		$options = array();
		$options = array_merge_recursive($options, $_options);
		$provider = $this->getProvider();
		try {
			$request = $provider->getAuthenticatedRequest($_type, 'https://www.googleapis.com/calendar/v3/' . trim($_request, '/'), $this->getAccessToken(), $options);
			$response = $provider->getResponse($request);
			if(!is_array($response)){
				return json_decode($response->getBody()->getContents(), true);
			}
			return $response;
		} catch (Exception $e) {

		}
		try {
			$request = $provider->getAuthenticatedRequest($_type, 'https://www.googleapis.com/calendar/v3/' . trim($_request, '/'), $this->getAccessToken(true), $options);
			return json_decode($provider->getResponse($request)->getBody()->getContents(), true);
		} catch (Exception $e) {
			if (strpos($e, "404 Not Found") === false) {
				throw new Exception($e);
			}
		}
	}

	public function listCalendar() {
		log::add('google_agenda', 'debug', "Fonction : " . __FUNCTION__);
		if( $this->ping()=="NOK"){
			log::add('google_agenda', 'debug', "Connection internet: NOK");
			log::add('google_agenda', 'debug', "Arrêt de la fonction");
			return;
		}
		log::add('google_agenda', 'debug', "Connection internet: OK");
		
		if ($this->getConfiguration('accessToken') == '') {
			return array();
		}
		$result = $this->request('GET', '/users/me/calendarList');
		return (isset($result['items'])) ? $result['items'] : array();
	}

	public function getEvents($_calendarId) {
		
		$result = $this->request('GET', '/calendars/' . $_calendarId . '/events?singleEvents=true&timeMin=' . urlencode(date(DATE_RFC3339, strtotime('-1 day'))) . '&timeMax=' . urlencode(date(DATE_RFC3339, strtotime('+1 week'))));
		return (isset($result['items'])) ? $result['items'] : array();
	}

	public function Synchronisation_google() {
		log::add('google_agenda', 'debug', "Fonction : " . __FUNCTION__);
		if($this->getIsEnable() == 0) {
			log::add('google_agenda', 'debug', "Equipement désactivé.");
			return;
		}
		if( $this->ping()=="NOK"){
			log::add('google_agenda', 'debug', "Connection internet: NOK");
			log::add('google_agenda', 'debug', "Arrêt de la fonction");
			return;
		}
		if (!is_array($this->getConfiguration('agendas')) || count($this->getConfiguration('agendas')) == 0) {
			log::add('google_agenda', 'debug', "Aucun angenda enregistré");
			return;
		}
		$agendas=$this->getConfiguration('agendas');
		foreach ($agendas as $agenda) {
			//log::add('google_agenda_test', 'debug',json_encode($agenda));
			foreach ($agenda as $key => $value) {
				if ($key == "agenda_id"){$agenda_id = $value;}
				if ($key == "checked"){$agenda_actif = intval($value);}
			}
			if ($agenda_actif == 0) {
				continue;
			}
			
			try {
				foreach ($this->getEvents($agenda_id) as $event) {
					//log::add('google_agenda_test', 'debug', json_encode($event));
					if(isset($event['summary'])){
						$Nom=$event['summary'];
					}else{
						$Nom='(Sans titre)';
					}
					if(isset($event['description'])){
						$Description=$event['description'];
					}else{
						$Description='aucun';
					}
					if(isset($event['start']['date'])){
						$Debut = $event['start']['date'];
						$Jour_entier = "Oui";
					}else{
						$Debut=date('Y-m-d H:i:s', strtotime($event['start']['dateTime']));
						$Jour_entier="Non";
					}
					if(isset($event['transparency'])){
						$Disponible = "Oui";
					}else{
						$Disponible = "Non";
					}
					if(isset($event['end']['date'])){
						$Fin=$event['end']['date'];
					}else{
						$Fin=date('Y-m-d H:i:s', strtotime($event['end']['dateTime']));
					}
					$evenements[] = array(
						'Nom_évènement' => $Nom,
						'Description' => $Description,
						'Debut' => $Debut,
						'Fin' => $Fin,
						'Disponible' => $Disponible,
						'Jour_entier' => $Jour_entier,
					);
				}
			} catch (Exception $e) {
				log::add('google_agenda', 'error', __('Erreur sur : ', __FILE__) . $calendarId . ' => ' . $e->getMessage());
				return;
			}
		}
		
		log::add('google_agenda', 'debug', 'evenements : ' . json_encode($evenements));
		if (count($evenements) > 0) {
			$this->setCache('evenements', $evenements);
		}
		$this->checkAndUpdateCmd('lastsync', date('Y-m-d H:i:s'));
		$this->checkAndUpdateCmd('maintenant', $this->recup_evenements_en_cours());
		$this->checkAndUpdateCmd('aujourdhui', $this->recup_evenements_du_jour());
		$this->checkAndUpdateCmd('demain', $this->recup_evenement_demain());
		$this->checkAndUpdateCmd('suivant', $this->recup_evenement_suivant_du_jour());
		}

	public function recup_evenements_en_cours() {
		$return = '';
		if (!is_array($this->getCache('evenements')) || count($this->getCache('evenements')) == 0) {
			return $return;
		}
		$now = strtotime('now');
		foreach ($this->getCache('evenements') as $evenement) {
			//log::add('google_agenda', 'debug',json_encode($evenement));
			if (strtotime($evenement['Debut']) <= $now && strtotime($evenement['Fin']) >= $now) {
				$return .= $evenement['Nom_évènement'] . ',';
				continue;
			}
		}
		log::add('google_agenda', 'debug', "Fonction : " . __FUNCTION__ . ": " .trim($return, ',') );
		return trim($return, ',');
	}

	public function recup_evenements_du_jour() {
		$return = '';
		if (!is_array($this->getCache('evenements')) || count($this->getCache('evenements')) == 0) {
			return $return;
		}
		$starttime = strtotime('00:00:00');
		$endtime = strtotime('23:59:59');
		foreach ($this->getCache('evenements') as $evenement) {
			$Jour_entier=false;
			$endtime_event = strtotime($evenement['Fin']);
			if($evenement['Jour_entier'] == "Oui"){
				$Jour_entier = true;
			}
			if ($endtime_event == strtotime('00:00:00') && strtotime($evenement['Debut']) <> strtotime('00:00:00')) {//Si fin à 00:00 et que ce n'est pas un évènement "instantané" alors on mets la fin à 23:59:59
				$endtime_event = $endtime_event - 1;
			}
			if (strtotime($evenement['Debut']) <= $endtime && $endtime_event >= $starttime) {
				if ($Jour_entier){
					$return .= "Toute la journée" . "->" .$evenement['Nom_évènement'] . ',';
				}else{
					$return .= date('H:i', strtotime($evenement['Debut']))."-". date('H:i', strtotime($evenement['Fin'])). "->" .$evenement['Nom_évènement'] . ',';
				}
				continue;
			}
		}
		log::add('google_agenda', 'debug', "Fonction : " . __FUNCTION__ . ": " .trim($return, ',') );
		return trim($return, ',');
	}

	public function recup_evenement_demain() {
		$return = '';
		if (!is_array($this->getCache('evenements')) || count($this->getCache('evenements')) == 0) {
			return $return;
		}
		$starttime = strtotime('+1 day 00:00:00');
		$endtime = strtotime('+1 day 23:59:59');
		foreach ($this->getCache('evenements') as $evenement) {
			//log::add('google_agenda', 'debug',json_encode($evenement));
			$fullday=false;
			$endtime_event = strtotime($evenement['Fin']);
			if($evenement['Jour_entier'] == "Oui"){
				$fullday = true;
			}
			if ($endtime_event == strtotime('+1 day 00:00:00') && strtotime($evenement['Debut']) <> strtotime('+1 day 00:00:00')) {//Si fin à 00:00 et que ce n'est pas un évènement "instantané" alors on mets la fin à 23:59:59
				$endtime_event = $endtime_event - 1;
			}
			if (strtotime($evenement['Debut']) <= $endtime && $endtime_event >= $starttime) {
				if ($fullday){
					$return .= "Toute la journée" . "->" .$evenement['Nom_évènement'] . ',';
				}else{
					$return .= date('H:i', strtotime($evenement['Debut']))."-". date('H:i', strtotime($evenement['Fin'])). "->" .$evenement['Nom_évènement'] . ',';
				}
				continue;
			}
		}
		log::add('google_agenda', 'debug', "Fonction : " . __FUNCTION__ . ": " .trim($return, ',') );
		return trim($return, ',');
	}
	
	public function recup_evenement_suivant_du_jour() {
		$return = '';
		if (!is_array($this->getCache('evenements')) || count($this->getCache('evenements')) == 0) {
			return $return;
		}
		$starttime = strtotime("now");
		$endtime = strtotime('+0 day 23:59:59');
		foreach ($this->getCache('evenements') as $evenement) {
			$starttime_event = strtotime($evenement['Debut']);
			$endtime_event = strtotime($evenement['Fin']);
			if($starttime_event>$starttime && $endtime_event<=$endtime){
				$return .= date('H:i', strtotime($evenement['Debut']))."-". date('H:i', strtotime($evenement['Fin'])). "->" .$evenement['Nom_évènement'] . ',';
				
			}
			//if($evenement['Jour_entier'] == "Oui" && $starttime_event<=$starttime && $endtime_event>=$endtime){
			//	$return .= "Toute la journée" . "->" .$evenement['Nom_évènement'] . ',';
			//}
			
			
		}
		log::add('google_agenda', 'debug', "Fonction : " . __FUNCTION__ . ": " .trim($return, ',') );
		return trim($return, ',');
	}
	
	public function recup_filtre(){
		//log::add('google_agenda', 'debug', "Fonction : " . __FUNCTION__);
		if($this->getIsEnable() == 0) {
			log::add('google_agenda', 'debug', "Equipement désactivé.");
			return;
		}
		
			$eqLogic_agenda=eqLogic::byId($this->getConfiguration("equipement"));
			$filtre=$this->getConfiguration("filtre");
			$sur_titre=$this->getConfiguration("sur_titre");
			$sur_contenu=$this->getConfiguration("sur_contenu");
			$trouve=false;
			$evenement_aujourdhui=$this->recup_infos_evenement($eqLogic_agenda,$filtre,true,$sur_titre,$sur_contenu, $trouve);
			
			
			if($trouve){
				log::add('google_agenda', 'debug', "evenement aujourdhui : Oui");
				log::add('google_agenda', 'debug', "trouvé : " . $trouve);
				log::add('google_agenda', 'debug', "heure de début :". date('H:i',$evenement_aujourdhui["debut"]));
				log::add('google_agenda', 'debug', "heure de fin :". date('H:i',$evenement_aujourdhui["fin"]));
				$this->checkAndUpdateCmd('debut',  date('H:i',$evenement_aujourdhui["debut"]));
				$this->checkAndUpdateCmd('fin',  date('H:i',$evenement_aujourdhui["fin"]));
				$this->checkAndUpdateCmd('aujourdhui', 1);
			}else{
				log::add('google_agenda', 'debug', "evenement aujourdhui : Non");
				$this->checkAndUpdateCmd('debut', "");
				$this->checkAndUpdateCmd('fin', "");
				$this->checkAndUpdateCmd('aujourdhui', 0);
			}
			
			$evenement_demain=$this->recup_infos_evenement($eqLogic_agenda,$filtre,false,$sur_titre,$sur_contenu, $trouve);
			if ($trouve){
				log::add('google_agenda', 'debug', "evenement demain : Oui");
				$this->checkAndUpdateCmd('demain', 1);
			}else{
				log::add('google_agenda', 'debug', "evenement demain : Non");
				$this->checkAndUpdateCmd('demain', 0);
			}
			//Vérification des commandes début
			$cmd_aujourdhui=cmd::byEqLogicIdAndLogicalId($this->getId(),"aujourdhui");
			if(!is_object($cmd_aujourdhui)){
				return;
			}
			if($cmd_aujourdhui->execCmd() == 1){
				$cmds = $this->getConfiguration('action_debut');
				foreach ($cmds as $cmd) {
					if ($cmd['cmd']==""){
						continue;
					}
					$execute_action = 0;
					if ($cmd['moment'] == "jour" && date('d/m/Y H:i',strtotime('now')) == date('d/m/Y H:i',strtotime('00:00:00'))){
						$execute_action = 1;
					}else if ($cmd['moment'] == "heure" &&  date('d/m/Y H:i',$evenement_aujourdhui["debut"]) == date('d/m/Y H:i',strtotime("now")) ){
						$execute_action =1;
					}
					if ($execute_action==1){
						try {
							$options = array();
							if (isset($cmd['options'])) {
								$options = $cmd['options'];
								log::add('google_agenda', 'debug', 'execution action début: ' . $cmd['cmd']);
								log::add('google_agenda', 'debug', 'execution action début: ' . implode(" " ,$cmd['options']));
								scenarioExpression::createAndExec('action', $cmd['cmd'], $options);
							}else{
								if (is_numeric (trim($cmd['cmd'], "#"))){
									$cmd=cmd::byId(trim($cmd['cmd'], "#"));
									if(is_object($cmd)){
										log::add('google_agenda', 'debug', 'execution action début: ' . $cmd->getHumanName());
										$cmd->execCmd();
									}
								}else{
									log::add('google_agenda', 'debug', 'execution action début: ' . $cmd['cmd']);
									log::add('google_agenda', 'debug', 'execution action début: ' . implode(" " ,$cmd['options']));
									scenarioExpression::createAndExec('action', $cmd['cmd'], $options);
								}
							}								
							
						}catch (Exception $e) {
							log::add('google_agenda', 'error', __('Erreur lors de l\'éxecution de ', __FILE__) . $cmd['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
						}
					}
					continue;
				}	
			}
			
			//Vérification des commandes fin
			$cmd_demain=cmd::byEqLogicIdAndLogicalId($this->getId(),"demain");
			if(!is_object($cmd_demain)){
				return;
			}
				
			$cmds = $this->getConfiguration('action_fin');
			foreach ($cmds as $cmd) {
				if ($cmd['cmd']==""){
					continue;
				}
				$execute_action = 0;
				if ($cmd['moment'] == "jour" && date('d/m/Y H:i',strtotime('now')) == date('d/m/Y H:i',strtotime('23:59:59'))){
					if($cmd_demain->execCmd() == 0){	
						$execute_action = 1;
					}else{
						//log::add('google_agenda_execution', 'debug', "Pas d'execution de l'action de fin du jour car il existe le même événement demain.");
					}
				}else if ($cmd['moment'] == "heure" &&  date('d/m/Y H:i',$evenement_aujourdhui["fin"]) == date('d/m/Y H:i',strtotime("now")) ){
					$execute_action =1;
				}
				if ($execute_action==1){
					try {
						$options = array();
						if (isset($cmd['options'])) {
							$options = $cmd['options'];
							//log::add('google_agenda_execution', 'debug', 'execution action fin: ' . $cmd['cmd']);
							//log::add('google_agenda_execution', 'debug', 'execution action fin: ' . implode(" " ,$cmd['options']));
							scenarioExpression::createAndExec('action', $cmd['cmd'], $options);
						}else{
							if (is_numeric (trim($cmd['cmd'], "#"))){
								$cmd=cmd::byId(trim($cmd['cmd'], "#"));
								if(is_object($cmd)){
									//log::add('google_agenda_execution', 'debug', 'execution action fin: ' . $cmd->getHumanName());
									$cmd->execCmd();
								}
							}else{
								//log::add('google_agenda_execution', 'debug', 'execution action fin: ' . $cmd['cmd']);
								//log::add('google_agenda_execution', 'debug', 'execution action fin: ' . implode(" " ,$cmd['options']));
								scenarioExpression::createAndExec('action', $cmd['cmd'], $options);
							}
						}
					}catch (Exception $e) {
						log::add('google_agenda', 'error', __('Erreur lors de l\'éxecution de ', __FILE__) . $cmd['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
					}
				}		
				continue;
			}
			
		
	}
	
	public function recup_infos_evenement($eqLogic_agenda,$filtre,$aujourdhui,$sur_titre,$sur_contenu, &$trouve) {
		//log::add('google_agenda', 'debug', "Fonction : " . __FUNCTION__);
		$return = "";
		$trouve=false;
		if (!is_array($eqLogic_agenda->getCache('evenements')) || count($eqLogic_agenda->getCache('evenements')) == 0) {
			return $return;
		}
		//log::add('google_agenda', 'debug', "aujourdhui : ". $aujourdhui);
		if ($aujourdhui){
			$starttime = strtotime('00:00:00');
			$endtime = strtotime('23:59:59');
		}else{
			$starttime = strtotime('+1 day 00:00:00');
			$endtime = strtotime('+1 day 23:59:59');
		}
		
		
		$return=[];
		$return["debut"]=PHP_INT_MAX;
		$return["fin"]=0;
		foreach ($eqLogic_agenda->getCache('evenements') as $evenement) {
			$endtime_event = strtotime($evenement['Fin']);
			if ($endtime_event == $starttime && strtotime($evenement['Debut']) <> $starttime) {//Si fin à 00:00 et que ce n'est pas un évènement "instantané" alors on mets la fin à 23:59:59
				$endtime_event = $endtime_event - 1;
			}
			if (strtotime($evenement['Debut']) <= $endtime && $endtime_event >= $starttime) {
				
				//if(strpos(strtolower($evenement['Nom_évènement']), strtolower($filtre))!== false){
					//log::add('google_agenda', 'debug', "evenement : ". $evenement['Nom_évènement']);
				//log::add('google_agenda', 'debug', 'evenement: ' . implode(" " ,$evenement));
				$recherche_dans = '';
				if ($sur_titre == 1){
					$recherche_dans .= $evenement['Nom_évènement'];
					//log::add('google_agenda', 'debug', "Nom_évènement : ". $evenement['Nom_évènement']);
				}
				if ($sur_contenu == 1){
					$recherche_dans .= $evenement['Description'];
					//log::add('google_agenda', 'debug', "Description : ". $evenement['Description']);
				}
				//log::add('google_agenda', 'debug', "filtre : ". $filtre);
				//log::add('google_agenda', 'debug', "recherche_dans : ". $recherche_dans);
				if(strpos(strtolower($recherche_dans), strtolower($filtre))!== false){
					$trouve=true;
					if ($return["debut"]>strtotime($evenement['Debut'])){
						$return["debut"] = strtotime($evenement['Debut']);
					}
					if ($return["fin"]<$endtime_event){
						$return["fin"] = $endtime_event;
					}
					//$return["fin"] = $endtime_event;
					
				}
				//continue;
			}
		}
		return $return;
	}
	
	public function ajout_filtre($name) {
		$eqLogic = new eqLogic();
		$eqLogic->setEqType_name('google_agenda');
		$eqLogic->setName($name);
		$eqLogic->setLogicalId($name);
		$eqLogic->setConfiguration('type_equipement', 'filtre');
		$eqLogic->setConfiguration('agendas', []);		
		$eqLogic->save();
	}
	
	public function ajout_agenda($name) {
		$eqLogic = new eqLogic();
		$eqLogic->setEqType_name('google_agenda');
		$eqLogic->setName($name);
		$eqLogic->setLogicalId($name);
		$eqLogic->setConfiguration('type_equipement', 'agenda');
		$eqLogic->setConfiguration('agendas', []);
		$eqLogic->save();
	}
	public function preUpdate() {
		$message = "";
		if($this->getConfiguration('type_equipement') == "filtre" && $this->getIsEnable()==1){
			
			if ($this->getConfiguration('filtre') == '') {
				$message .='Le champ "Filtre d\'évènement" ne peut être vide<br>';
			}
			if ($this->getConfiguration('sur_contenu') == 0 && $this->getConfiguration('sur_titre') == 0) {
				$message .= 'Veuillez selectionner une option dans le champ "Rechercher dans"<br>';
			}
			if ($this->getConfiguration('equipement') == '') {
				$message .= 'Le champ "Equipement dans lequel rechercher" ne peut pas être "aucun"<br>';
			}
		}else{
			if($this->getConfiguration('type_equipement') == "agenda" &&$this->getIsEnable()==1){
				if ($this->getConfiguration('client_id') == '') {
					$message .= 'Le champ "client_id" ne peut pas être vide<br>';
				}
				if ($this->getConfiguration('client_secret') == '') {
					$message .= 'Le champ "Secret key" ne peut pas être vide<br>';
				}
				
			}
		}
		if ($message !=""){
			throw new Exception($message);
		}
	}
	public function postSave() {
		
		if ($this->getConfiguration('type_equipement') == 'agenda' ) {
			$cmd = $this->getCmd(null, 'maintenant');
			if (!is_object($cmd)) {
				$cmd = new google_agendaCmd();
				$cmd->setLogicalId('maintenant');
				$cmd->setIsVisible(1);
				$cmd->setName(__('Evènements en cours', __FILE__));
				$cmd->setTemplate('dashboard', 'line');
				$cmd->setTemplate('mobile', 'line');
				$cmd->setType('info');
				$cmd->setSubType('string');
				$cmd->setEqLogic_id($this->getId());
				$cmd->save();
			}

			$cmd = $this->getCmd(null, 'aujourdhui');
			if (!is_object($cmd)) {
				$cmd = new google_agendaCmd();
				$cmd->setLogicalId('aujourdhui');
				$cmd->setIsVisible(1);
				$cmd->setName(__('Evènements du jour', __FILE__));
				$cmd->setTemplate('dashboard', 'line');
				$cmd->setTemplate('mobile', 'line');
				$cmd->setType('info');
				$cmd->setSubType('string');
				$cmd->setEqLogic_id($this->getId());
				$cmd->save();
			}
			
			$cmd = $this->getCmd(null, 'demain');
			if (!is_object($cmd)) {
				$cmd = new google_agendaCmd();
				$cmd->setLogicalId('demain');
				$cmd->setIsVisible(1);
				$cmd->setName(__('Evènements de demain', __FILE__));
				$cmd->setTemplate('dashboard', 'line');
				$cmd->setTemplate('mobile', 'line');
				$cmd->setType('info');
				$cmd->setSubType('string');
				$cmd->setEqLogic_id($this->getId());
				$cmd->save();
			}
			$cmd = $this->getCmd(null, 'suivant');
			if (!is_object($cmd)) {
				$cmd = new google_agendaCmd();
				$cmd->setLogicalId('suivant');
				$cmd->setIsVisible(1);
				$cmd->setName(__('Prochain évènement', __FILE__));
				$cmd->setTemplate('dashboard', 'line');
				$cmd->setTemplate('mobile', 'line');
				$cmd->setType('info');
				$cmd->setSubType('string');
				$cmd->setEqLogic_id($this->getId());
				$cmd->save();
			}
			$cmd = $this->getCmd(null, 'lastsync');
			if (!is_object($cmd)) {
				$cmd = new google_agendaCmd();
				$cmd->setLogicalId('lastsync');
				$cmd->setIsVisible(1);
				$cmd->setName(__('Date synchronisation', __FILE__));
				$cmd->setTemplate('dashboard', 'line');
				$cmd->setTemplate('mobile', 'line');
				$cmd->setType('info');
				$cmd->setSubType('string');
				$cmd->setEqLogic_id($this->getId());
				$cmd->save();
			}
			$cmd = $this->getCmd(null, 'refresh');
			if (!is_object($cmd)) {
				$cmd = new google_agendaCmd();
				$cmd->setLogicalId('refresh');
				$cmd->setIsVisible(1);
				$cmd->setName(__('Rafraîchir', __FILE__));
				$cmd->setType('action');
				$cmd->setSubType('other');
				$cmd->setEqLogic_id($this->getId());
				$cmd->save();
			}
			$this->setConfiguration("agendas",'[]');
			$this->Synchronisation_google();
		}else{
			$cmd = $this->getCmd(null, 'demain');
			if (!is_object($cmd)) {
				$cmd = new google_agendaCmd();
				$cmd->setLogicalId('demain');
				$cmd->setName('Evenement demain');
				$cmd->setEqLogic_id($this->getId());
				$cmd->setType('info');
				$cmd->setSubType('binary');
				$cmd->save(); 
			}
			
			$cmd = $this->getCmd(null, 'aujourdhui');
			if (!is_object($cmd)) {
				$cmd = new google_agendaCmd();
				$cmd->setLogicalId('aujourdhui');
				$cmd->setName('Evenement aujourdhui');
				$cmd->setEqLogic_id($this->getId());
				$cmd->setType('info');
				$cmd->setSubType('binary');
				$cmd->save();
				$this->checkAndUpdateCmd('aujourdhui', 0);
			}

			$cmd = $this->getCmd(null, 'debut');
			if (!is_object($cmd)) {
				$cmd = new google_agendaCmd();
				$cmd->setLogicalId('debut');
				$cmd->setName('Heure de début');
				$cmd->setEqLogic_id($this->getId());
				$cmd->setType('info');
				$cmd->setSubType('string');
				$cmd->save();
				$this->checkAndUpdateCmd('demain', 0);
			}
			
			$cmd = $this->getCmd(null, 'fin');
			if (!is_object($cmd)) {
				$cmd = new google_agendaCmd();
				$cmd->setLogicalId('fin');
				$cmd->setName('Heure de fin');
				$cmd->setEqLogic_id($this->getId());
				$cmd->setType('info');
				$cmd->setSubType('string');
				$cmd->save();
			}
			$cmd = $this->getCmd(null, 'refresh');
			if (!is_object($cmd)) {
				$cmd = new google_agendaCmd();
				$cmd->setLogicalId('refresh');
				$cmd->setIsVisible(1);
				$cmd->setName(__('Rafraîchir', __FILE__));
				$cmd->setType('action');
				$cmd->setSubType('other');
				$cmd->setEqLogic_id($this->getId());
				$cmd->save();
			}
			
			eqLogic::byId($this->getConfiguration("equipement"))->Synchronisation_google();
			$this->recup_filtre();
		}
		
		
	}

  /*     * *********************Methode d'instance************************* */
}

class google_agendaCmd extends cmd {
  /*     * *************************Attributs****************************** */

  /*     * ***********************Methode static*************************** */

  /*     * *********************Methode d'instance************************* */

	public function execute($_options = array()) {
		if ($this->getLogicalId() == 'refresh') {
			log::add('google_agenda', 'debug', "********************************************************************");
			log::add('google_agenda', 'debug', "Rafraichir");
			$eqLogic = $this->getEqLogic();
			if ($eqLogic->getConfiguration("type_equipement") =="agenda"){
				log::add('google_agenda', 'debug', "Nom équipement:" . $eqLogic->getHumanName());
				$eqLogic->Synchronisation_google();
			}
			if ($eqLogic->getConfiguration("type_equipement") =="filtre"){
					log::add('google_agenda', 'debug', "Nom équipement:" . $eqLogic->getHumanName());
					$eqLogic->recup_filtre();
					
				}
		}
	}

  /*     * **********************Getteur Setteur*************************** */

}

class googleProvider extends AbstractProvider {
  use BearerAuthorizationTrait;
  const ACCESS_TOKEN_RESOURCE_OWNER_ID = 'id';
  protected $accessType;
  protected $hostedDomain;
  protected $defaultUserFields = [
    'id',
    'name(familyName,givenName)',
    'displayName',
    'emails/value',
    'image/url',
  ];
  protected $userFields = [];

  public function getBaseAuthorizationUrl() {
    return 'https://accounts.google.com/o/oauth2/auth';
  }

  public function getBaseAccessTokenUrl(array $params) {
    return 'https://accounts.google.com/o/oauth2/token';
  }

  public function getResourceOwnerDetailsUrl(AccessToken $token) {
    $fields = array_merge($this->defaultUserFields, $this->userFields);
    return 'https://www.googleapis.com/plus/v1/people/me?' . http_build_query([
      'fields' => implode(',', $fields),
      'alt' => 'json',
    ]);
  }

  protected function getAuthorizationParameters(array $options) {
    $params = array_merge(
      parent::getAuthorizationParameters($options),
      array_filter([
        'hd' => $this->hostedDomain,
        'access_type' => $this->accessType,
        'authuser' => '-1',
      ])
    );
    return $params;
  }

  protected function getDefaultScopes() {
    return [
      'email',
      'openid',
      'profile',
      'https://www.googleapis.com/auth/calendar.readonly',
    ];
  }

  protected function getScopeSeparator() {
    return ' ';
  }

  protected function checkResponse(ResponseInterface $response, $data) {
    if (!empty($data['error'])) {
      $code = 0;
      $error = $data['error'];
      if (is_array($error)) {
        $code = $error['code'];
        $error = $error['message'];
      }
      throw new IdentityProviderException($error, $code, $data);
    }
  }

  protected function createResourceOwner(array $response, AccessToken $token) {
    return new googleOwner($response);
  }
}

class googleOwner implements ResourceOwnerInterface {
  protected $response;

  public function __construct(array $response) {
    $this->response = $response;
  }
  public function getId() {
    return $this->response['id'];
  }

  public function getName() {
    return $this->response['displayName'];
  }

  public function getFirstName() {
    return $this->response['name']['givenName'];
  }

  public function getLastName() {
    return $this->response['name']['familyName'];
  }

  public function getEmail() {
    if (!empty($this->response['emails'])) {
      return $this->response['emails'][0]['value'];
    }
  }

  public function getAvatar() {
    if (!empty($this->response['image']['url'])) {
      return $this->response['image']['url'];
    }
  }

  public function toArray() {
    return $this->response;
  }
}

