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

  static function add_log($level = 'debug',$Log,$eqLogic=null){
    if (is_array($Log)) $Log = json_encode($Log);
    if(count(debug_backtrace(false, 2)) == 1){
      $function_name = debug_backtrace(false, 2)[0]['function'];
      $ligne = debug_backtrace(false, 2)[0]['line'];
    }else{
      $function_name = debug_backtrace(false, 2)[1]['function'];
      $ligne = debug_backtrace(false, 2)[0]['line'];
    }
    $msg =  $function_name .' (' . $ligne . '): '.$Log;
    if( $eqLogic!=null){
       $nom_eq= google_agenda::supp_accents(str_replace(" " , "_",str_replace("[" , "_",str_replace("]" , "",$eqLogic->getHumanName(false)))));
       log::add('google_agenda'.$nom_eq  , $level,$msg);
     
    }else{
      log::add('google_agenda'  , $level,$msg);
    }


  }
  static function supp_accents( $str, $charset='utf-8' ) {
    $str = htmlentities( $str, ENT_NOQUOTES, $charset );
    $str = preg_replace( '#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str );
    $str = preg_replace( '#&([A-za-z]{2})(?:lig);#', '\1', $str );
    $str = preg_replace( '#&[^;]+;#', '', $str );
    return $str;
  }
  public static function cron() {
    foreach (self::byType('google_agenda') as $eqLogic) {
      try {
        if ($eqLogic->getConfiguration("type_equipement") =="filtre" && $eqLogic->getIsEnable()){
          
          //self::add_log("debug", "--------------------------------------------------------------------",$eqLogic);
          //   self::add_log("debug",$nb);
         // self::add_log("debug","Nom équipement: " . $eqLogic->getHumanName(),$eqLogic);
          $eqLogic->recup_filtre(false);
        }
      } catch (Exception $e) {
        self::add_log("warning",'Erreur sur : '. $eqLogic->getHumanName() . ' => ' . $e->getMessage(),$eqLogic);
      }
    }
  }
  public static function cron30() {

   foreach (self::byType('google_agenda') as $eqLogic) {
      try {
        if ($eqLogic->getConfiguration("type_equipement") =="agenda"){
          //self::add_log("debug","--------------------------------------------------------------------",$eqLogic);
          //self::add_log("debug","Nom équipement: " . $eqLogic->getHumanName(),$eqLogic);

          $eqLogic->Synchronisation_google();


        }
      } catch (Exception $e) {
        self::add_log("warning",'Erreur sur : '. $eqLogic->getHumanName() . ' => ' . $e->getMessage(),$eqLogic);
      }
    }
    //self::add_log("debug","********************************************************************",$eqLogic);
  }

  public static function ping() {
    $ping = "NOK";
    $exec_string = 'sudo ping -n -c 1 -t 255 8.8.8.8';
    exec($exec_string, $output, $return);
    $output = array_values(array_filter($output));

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
      if (is_numeric(strpos($e, "404 Not Found"))){
        throw new Exception($e);
      }

    }
    
  }

  public function listCalendar() {

    if( $this->ping()=="NOK"){
      self::add_log("debug","Connection internet: NOK",$this);
     self::add_log("debug","Arrêt de la fonction",$this);

      return;
    }
    //self::add_log("debug","Connection internet: OK",$this);

    if ($this->getConfiguration('accessToken') == '') {
      return array();
    }
    $result = $this->request('GET', '/users/me/calendarList');
    return (isset($result['items'])) ? $result['items'] : array();
  }

  public function getEvents($_calendarId) {
    $result = $this->request('GET', '/calendars/' . $_calendarId . '/events?singleEvents=true&orderBy=startTime&timeMin=' . urlencode(date(DATE_RFC3339, strtotime('-1 day'))) . '&timeMax=' . urlencode(date(DATE_RFC3339, strtotime('+1 day'))));
    return (isset($result['items'])) ? $result['items'] : array();
  }

  public function Synchronisation_google() {

    if($this->getIsEnable() == 0) {
      self::add_log("debug","Equipement désactivé.",$this);
      return;
    }
    if( $this->ping()=="NOK"){
      self::add_log("debug","Connection internet: NOK",$this);
      self::add_log("debug","Arrêt de la fonction",$this);
      return;
    }
    if (!is_array($this->getConfiguration('agendas')) || count($this->getConfiguration('agendas')) == 0) {
      self::add_log("debug","Aucun angenda enregistré",$this);
      return;
    }

    $agendas=$this->getConfiguration('agendas');
    foreach ($agendas as $agenda) {
      //self::add_log("debug",json_encode($agenda),$this);
      foreach ($agenda as $key => $value) {
        if ($key == "agenda_id"){
          $agenda_id = $value;
          //self::add_log("debug","agenda_id: ".$agenda_id,$this);
        }
        if ($key == "checked"){
          $agenda_actif = intval($value);
          //self::add_log("debug","agenda_actif: ".$agenda_actif,$this);
        }
        if ($key == "nom"){
          $nom_agenda = $value;
          //self::add_log("debug","nom_agenda: ".$nom_agenda,$this);
        }
      }
      if ($agenda_actif == 0) {
        //self::add_log("debug","Agenda inactif",$this);
        continue;
      }

      try {

        foreach ($this->getEvents($agenda_id) as $event) {
          //self::add_log("debug", json_encode($this->getEvents($agenda_id)),$this);
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
          
      
    //$evenements=array_reverse($evenements);

      } catch (Exception $e) {
        self::add_log("info",'Erreur sur : '. $nom_agenda ,$this); 
        self::add_log("info"," Suppression de l'agenda",$this);
        return;
      }
    }
    //self::add_log("debug",'evenements : ' . json_encode($evenements),$this);
    if (count($evenements) > 0) {
      $this->setCache('evenements', $evenements);
    }
    $this->setCache('evenements', $evenements);
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
      //self::add_log("debug",json_encode($evenement),$this);
      if (strtotime($evenement['Debut']) <= $now && strtotime($evenement['Fin']) >= $now) {
        $return .= $evenement['Nom_évènement'] . ',';
        continue;
      }
    }
    if(trim($return, ',')!=""){
      //self::add_log("debug",trim($return, ',') $this);
    }

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
    if(trim($return, ',')!=""){
      //self::add_log("debug",trim($return, ','),$this);
    }
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
      //self::add_log("debug","Evènement demain",$this);
      //self::add_log("debug",json_encode($evenement),$this);
      $fullday=false;
      $endtime_event = strtotime($evenement['Fin']);
      if($evenement['Jour_entier'] == "Oui"){
        $fullday = true;
      }
      if ($endtime_event == strtotime('+1 day 00:00:00') && strtotime($evenement['Debut']) <> strtotime('+1 day 00:00:00')) {//Si fin à 00:00 et que ce n'est pas un évènement "instantané" alors on mets la fin à 23:59:59
        $endtime_event = $endtime_event - 1;
      }
       if (strtotime($evenement['Debut']) <= strtotime('+0 day 23:59:59') && $evenement['Jour_entier'] != "Oui") {
          //self::add_log("debug","événement de la veille",$this);
         //return $return;
       }
      if (strtotime($evenement['Debut']) <= $endtime && $endtime_event >= $starttime) {
        if ($fullday){
         // self::add_log("debug",$starttime . "////" . $endtime ,$this);
          $return .= "Toute la journée" . "->" .$evenement['Nom_évènement'] . ',';
        }else{
         // self::add_log("debug",$starttime . "////" . $endtime ,$this);
          $return .= date('H:i', strtotime($evenement['Debut']))."-". date('H:i', strtotime($evenement['Fin'])). "->" .$evenement['Nom_évènement'] . ',';
        }
        continue;
      }
    }
    //self::add_log("debug",trim($return, ','),$this);
    if(trim($return, ',')!=""){
      //self::add_log("debug",trim($return, ','),$this);
    }
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
    if(trim($return, ',')!=""){
      //self::add_log("debug",trim($return, ','),$this);
    }
    return trim($return, ',');
  }

  public function recup_filtre($Force_execution){
    if($this->getIsEnable() == 0) {
      //self::add_log("debug","Equipement désactivé.",$this);
      
      $this->checkAndUpdateCmd('Commande_debut', 0);
      return;
    }
   
    
    $cmd_demain = cmd::byEqLogicIdAndLogicalId($this->getId(),"demain");
    $eqLogic_agenda = eqLogic::byId($this->getConfiguration("equipement"));
    $filtre = $this->getConfiguration("filtre");
    $sur_titre = $this->getConfiguration("sur_titre");
    $sur_contenu = $this->getConfiguration("sur_contenu");
    $trouve = false;
    $cmd_aujourdhui = cmd::byEqLogicIdAndLogicalId($this->getId(),"aujourdhui");
    $cmd_hier = cmd::byEqLogicIdAndLogicalId($this->getId(),"hier");
    $cmd_demain = cmd::byEqLogicIdAndLogicalId($this->getId(),"demain");
    $cmd_commande_debut = cmd::byEqLogicIdAndLogicalId($this->getId(),"Commande_debut");
    $cmd_maintenant = cmd::byEqLogicIdAndLogicalId($this->getId(),"maintenant");
    if (!$Force_execution){
      //self::add_log("debug", "********************************************************************",$this);
      //self::add_log("debug","Rafraichir " . $this->getName(),$this);
    }
    if(!is_object($cmd_aujourdhui) || !is_object($cmd_hier) || !is_object($cmd_demain) || !is_object($cmd_commande_debut)|| !is_object($cmd_maintenant)){
      self::add_log("debug","Ajout des commandes manquantes",$this);
      $this->save();
      return;
    }
    $evenement_aujourdhui=$this->recup_infos_evenement($eqLogic_agenda,$filtre,"aujourdhui",$sur_titre,$sur_contenu, $trouve);
    
    if($trouve){
      
      /*self::add_log("debug","evenement aujourdhui : Oui",$this);
      self::add_log("debug","heure actuelle :". date('d/m/Y H:i',strtotime("now")),$this);
      self::add_log("debug","heure de début :". date('d/m/Y H:i',$evenement_aujourdhui["debut"]),$this);
      self::add_log("debug","heure de fin :". date('d/m/Y H:i',$evenement_aujourdhui["fin"]),$this);*/
      if($evenement_aujourdhui["Jour_entier"] == "Oui"){
        //self::add_log("debug","évenement jour entier",$this);
        //self::add_log("debug","evenement en cours : Oui",$this);
        $this->checkAndUpdateCmd('maintenant', 1);
      }else if (date('d/m/Y H:i',strtotime("now"))> date('d/m/Y H:i',$evenement_aujourdhui["fin"])){
        //self::add_log("debug","evenement en cours : Non",$this);
        $this->checkAndUpdateCmd('maintenant', 0);
      }else if (date('d/m/Y H:i',strtotime("now"))>= date('d/m/Y H:i',$evenement_aujourdhui["debut"])){
         //self::add_log("debug","evenement en cours : Oui",$this);
         $this->checkAndUpdateCmd('maintenant', 1);
      }else{
        //self::add_log("debug","evenement en cours : Non",$this);
        $this->checkAndUpdateCmd('maintenant', 0);
      }
      $this->checkAndUpdateCmd('debut',  date('H:i',$evenement_aujourdhui["debut"]));
      $this->checkAndUpdateCmd('fin',  date('H:i',$evenement_aujourdhui["fin"]));
      $this->checkAndUpdateCmd('aujourdhui', 1);
    }else{
      //self::add_log("debug","evenement aujourdhui : Non",$this);
      $this->checkAndUpdateCmd('debut', "");
      $this->checkAndUpdateCmd('fin', "");
      $this->checkAndUpdateCmd('aujourdhui', 0);
      $this->checkAndUpdateCmd('maintenant', 0);
    }
        
    $evenement_demain=$this->recup_infos_evenement($eqLogic_agenda,$filtre,"demain",$sur_titre,$sur_contenu, $trouve);
    if ($trouve){
      /*self::add_log("debug",$filtre,$this);
      self::add_log("debug","evenement demain : Oui",$this);
      self::add_log("debug","heure de début :". date('H:i',$evenement_demain["debut"]),$this);
      self::add_log("debug","heure de fin :". date('H:i',$evenement_demain["fin"]),$this);*/
      $this->checkAndUpdateCmd('demain', 1);
    }else{
      //self::add_log("debug",$filtre . " : evenement demain : Non",$this);
      $this->checkAndUpdateCmd('demain', 0);
    }
    
    $evenement_hier=$this->recup_infos_evenement($eqLogic_agenda,$filtre,"hier",$sur_titre,$sur_contenu, $trouve);

    $cmd_hier=cmd::byEqLogicIdAndLogicalId($this->getId(),"hier");
    if(!is_object($cmd_hier)){
      return;
    }
    if ($trouve){
      //self::add_log("debug","evenement hier : Oui",$this);
      $this->checkAndUpdateCmd('hier', 1);
    }else{
      //self::add_log("debug","evenement hier : Non",$this);
      $this->checkAndUpdateCmd('hier', 0);
    }
    
    if (!$Force_execution){
      //self::add_log("debug","Fin Rafraichir " . $this->getName(),$this);
    }
    //Vérification des commandes début
    //if($cmd_maintenant->execCmd() == 0 & $cmd_commande_debut->execCmd() == 0 & $Force_execution== false){
      //self::add_log("debug","Aucune execution d'action de fin car pas de filtre hier et aujourd'hui (" . $this->getName() . ")",$this);
      //return;
   // }
    /*if($cmd_maintenant->execCmd() == 1 & $cmd_commande_debut->execCmd() == 1 & $Force_execution== false){
      //self::add_log("debug","Aucune execution d'action de debut car filtre existant hier et aujourd'hui (" . $this->getName() . ")",$this);
      return;
    }*/
	  $commane_debut = $cmd_commande_debut->execCmd();
    if($cmd_aujourdhui->execCmd() == 1 && ($cmd_commande_debut->execCmd() == 0 || $Force_execution)){
      self::add_log("debug", "********************************************************************",$this);
      $cmds = $this->getConfiguration('action_debut');
      foreach ($cmds as $cmd) {
        if ($cmd['cmd']==""){
          continue;
        }
        $execute_action = 0;
        //self::add_log("debug",$cmd['cmd']." moment: ". $cmd['moment'],$this);
  
        if ($cmd['moment'] == "jour" && date('d/m/Y H:i',strtotime('now')) >= date('d/m/Y H:i',strtotime('00:00:00'))){
          $execute_action = 1;
        }else if ($cmd['moment'] == "heure" &&  date('d/m/Y H:i',$evenement_aujourdhui["debut"]) <= date('d/m/Y H:i',strtotime("now")) ){
          $execute_action = 1;
        }
        
     
        if ($execute_action==1){
          self::add_log("debug","Execution actions début" ,$this);
          $this->checkAndUpdateCmd('Commande_debut', 1);
          try {
            $options = array();
            if (isset($cmd['options'])) {
              if (is_numeric (trim($cmd['cmd'], "#"))){
                $options = $cmd['options'];
                $cmd1=cmd::byId(trim($cmd['cmd'], "#"));
                self::add_log("debug",'execution action début: ' . $cmd1->getHumanName(). ': ' . implode(" " ,$cmd['options']),$this);
                scenarioExpression::createAndExec('action', $cmd['cmd'], $options);
              }else{
                 if (is_numeric (trim($cmd['cmd'], "#"))){
                  $execute=false;
                	$options = $cmd['options'];
                    $cmd1=cmd::byId(trim($cmd['cmd'], "#"));
                    self::add_log("debug",'execution action début: ' . $cmd1->getHumanName(). ': ' . implode(" " ,$cmd['options']),$this);
                    scenarioExpression::createAndExec('action', $cmd['cmd'], $options);
                  }else{
                    
                    $options = $cmd['options'];
                    if ($cmd['cmd'] == 'equipement'){$
                      $i=0;
                      $mess='';
                      $execute=true;
                      $eqLogic="";
                      foreach ($options as $value) {

                          if($i == 0){
                            
                            $eqLogic_id=trim($value, "#eqLogic");
                            $eqLogic=eqLogic::byId($eqLogic_id);
                            $value=$eqLogic->getHumanName();
                          }

                          if ($value == "activate" && $eqLogic->getIsEnable() == 1) {
                            self::add_log("debug",'Pas execution action début: ' . $cmd['cmd'] . ': ' . $mess . " car l'équipement est déjà actif",$eqLogic);
                            $execute=false;
                          }
                          if ($value == "deactivate" && $eqLogic->getIsEnable() == 0) {
                            self::add_log("debug",'Pas execution action début: ' . $cmd['cmd'] . ': ' . $mess . " car l'équipement est déjà désactivé",$eqLogic);
                            $execute=false;
                          }
                          $mess = $mess . " " . $value;
                          $i = $i+1;
                      }
                      if($execute){
                         self::add_log("debug",'execution action début: ' . $cmd['cmd'] . ': ' . $mess,$eqLogic);
                      }
                     
                   
                  }else{
                    $execute=true;
                    self::add_log("debug",'execution action début: ' . $cmd['cmd'] . ': ' . implode(" " ,$cmd['options']),$this);
                   
                  }
                   	
              
                    if ($execute){
                      scenarioExpression::createAndExec('action', $cmd['cmd'], $options);
                    }
                    
                }
              }
            }else{
              if (is_numeric (trim($cmd['cmd'], "#"))){
                $cmd=cmd::byId(trim($cmd['cmd'], "#"));
                if(is_object($cmd)){
                  self::add_log("debug",'execution action début: ' . $cmd->getHumanName(),$eqLogic);
                  $cmd->execCmd();
                }
              }else{
                self::add_log("debug",'execution action début: ' . $cmd['cmd'] . ': ' . implode(" " ,$cmd['options']),$eqLogic);
                scenarioExpression::createAndExec('action', $cmd['cmd'], $options);
              }
            }								

          }catch (Exception $e) {
            self::add_log("error",'Erreur lors de l\'éxecution de '. $cmd['cmd'] . ' Détails : '. $e->getMessage(),$eqLogic);
          }
        }
        continue;
      }	
    }

    //Vérification des commandes fin
    if($cmd_aujourdhui->execCmd() == 0  && ($cmd_commande_debut->execCmd() == 1 || $Force_execution)){
 
      $cmds = $this->getConfiguration('action_fin');
      foreach ($cmds as $cmd) {
        if ($cmd['cmd']==""){
          continue;
        }
        $execute_action = 0;

        if ($cmd['moment'] == "jour" && date('d/m/Y H:i',strtotime('now')) >= date('d/m/Y H:i',strtotime('00:00:00'))){
          if($cmd_aujourdhui->execCmd() == 0){	
            $execute_action = 1;
          }else{
            //self::add_log("debug","Pas d'execution de l'action de fin du jour car il existe le même événement aujourd'hui.",$this);
          }

        }else if ($cmd['moment'] == "heure" &&  date('d/m/Y H:i',$evenement_hier["fin"]) <= date('d/m/Y H:i',strtotime("now")) ){
          $execute_action =1;
        }
        if ($execute_action==1){
          $this->checkAndUpdateCmd('Commande_debut', 0);
          //self::add_log("debug","Execution des actions de fin car filtre existant hier mais pas aujourd'hui (" . $this->getName() . ")",$this);
          try {
            $options = array();
            if (isset($cmd['options'])) {
              if (is_numeric (trim($cmd['cmd'], "#"))){
                $options = $cmd['options'];
                $cmd1=cmd::byId(trim($cmd['cmd'], "#"));
                self::add_log("debug",'execution action début: ' . $cmd1->getHumanName(). ': ' . implode(" " ,$cmd['options']),$this);
                scenarioExpression::createAndExec('action', $cmd['cmd'], $options);
              }else{
                 if (is_numeric (trim($cmd['cmd'], "#"))){
                	$options = $cmd['options'];
                    $cmd1=cmd::byId(trim($cmd['cmd'], "#"));
                    self::add_log("debug",'execution action début: ' . $cmd1->getHumanName(). ': ' . implode(" " ,$cmd['options']),$this);
                    scenarioExpression::createAndExec('action', $cmd['cmd'], $options);
                  }else{
                    $options = $cmd['options'];
                    if ($cmd['cmd'] == 'equipement'){
                      $i=0;
                      $mess='';
                      $execute=true;
                      foreach ($options as $value) {
                          if($i == 0){
                            $value=eqLogic::byId(trim($value, "#eqLogic"))->getHumanName();
                            if ($value == "activate" || $value == "activate"){
                              if($cmd['cmd']){
                                $cmd1=cmd::byId(trim($cmd['cmd'], "#"));
                                $cmd1->execCmd();
                                self::add_log("debug",'test: ' . implode($cmd1),$this);
                              }
                              $execute=false;
                            }
                          }
                            $mess = $mess . " " . $value;
                        $i = $i+1;
                      }

                      self::add_log("debug",'execution action début: ' . $cmd['cmd'] . ': ' . $mess,$this);
                   
                   }else{
                      self::add_log("debug",'execution action début: ' . $cmd['cmd'] . ': ' . implode(" " ,$cmd['options']),$this);
                   
                   }
                   	
              
                    
                    scenarioExpression::createAndExec('action', $cmd['cmd'], $options);
                   }
                }
            }else{
              if (is_numeric (trim($cmd['cmd'], "#"))){
                $cmd=cmd::byId(trim($cmd['cmd'], "#"));
                if(is_object($cmd)){
                  self::add_log("debug",'execution action début: ' . $cmd->getHumanName(),$this);
                  $cmd->execCmd();
                }
              }else{
                self::add_log("debug",'execution action début: ' . $cmd['cmd'] . ': ' . implode(" " ,$cmd['options']),$this);
                scenarioExpression::createAndExec('action', $cmd['cmd'], $options);
              }
            }			
          }catch (Exception $e) {
            self::add_log("error", 'Erreur lors de l\'éxecution de ' . $cmd['cmd'] . ' Détails : ' . $e->getMessage(),$this);
          }
        }		
        continue;
      }
    }
   //Vérification si les commandes de début on été executée, si evenement est supprimé, execution des action de fin
    if($cmd_aujourdhui->execCmd() == 0 & $cmd_hier->execCmd() == 0 & $cmd_commande_debut->execCmd() == 1){
      $this->checkAndUpdateCmd('Commande_debut', 0);
       $cmds = $this->getConfiguration('action_fin');
      foreach ($cmds as $cmd) {
        if ($cmd['cmd']==""){
          continue;
        }
        
        //self::add_log("debug","Execution des actions de fin car filtre existant hier mais pas aujourd'hui (" . $this->getName() . ")",$this);
        
        try {
          $options = array();
            if (isset($cmd['options'])) {
              if (is_numeric (trim($cmd['cmd'], "#"))){
                $options = $cmd['options'];
                $cmd1=cmd::byId(trim($cmd['cmd'], "#"));
                self::add_log("debug",'execution action fin: ' . $cmd1->getHumanName(). ': ' . implode(" " ,$cmd['options']),$this);
                scenarioExpression::createAndExec('action', $cmd['cmd'], $options);
              }else{
                 if (is_numeric (trim($cmd['cmd'], "#"))){
                	$options = $cmd['options'];
                    $cmd1=cmd::byId(trim($cmd['cmd'], "#"));
                    self::add_log("debug",'execution action fin: ' . $cmd1->getHumanName(). ': ' . implode(" " ,$cmd['options']),$this);
                    scenarioExpression::createAndExec('action', $cmd['cmd'], $options);
                  }else{
                    $options = $cmd['options'];
                    if ($cmd['cmd'] == 'equipement'){
                      $i=0;
                      $mess='';
                      foreach ($options as $value) {
                          if($i == 0){
                            $value=eqLogic::byId(trim($value, "#eqLogic"))->getHumanName();
                          }
                            $mess = $mess . " " . $value;
                        $i = $i+1;
                      }
                      self::add_log("debug",'execution action fin: ' . $cmd['cmd'] . ': ' . $mess,$this);
                   
                   }else{
                      self::add_log("debug",'execution action fin: ' . $cmd['cmd'] . ': ' . implode(" " ,$cmd['options']),$this);
                   
                   }
                   	
              
                    
                    scenarioExpression::createAndExec('action', $cmd['cmd'], $options);
                   }
                }
            }else{
              if (is_numeric (trim($cmd['cmd'], "#"))){
                $cmd=cmd::byId(trim($cmd['cmd'], "#"));
                if(is_object($cmd)){
                  self::add_log("debug",'execution action fin: ' . $cmd->getHumanName(),$this);
                  $cmd->execCmd();
                }
              }else{
                self::add_log("debug",'execution action fin: ' . $cmd['cmd'] . ': ' . implode(" " ,$cmd['options']),$this);
                scenarioExpression::createAndExec('action', $cmd['cmd'], $options);
              }
            }	
        }catch (Exception $e) {
          self::add_log("error", 'Erreur lors de l\'éxecution de ' . $cmd['cmd'] . ' Détails : ' . $e->getMessage(),$this);
        }

        continue;
      }
    }
    
  }

  public static function recup_infos_evenement($eqLogic_agenda,$filtre,$jour,$sur_titre,$sur_contenu, &$trouve) {
    $return = "";
    $trouve=false;
    if (!is_array($eqLogic_agenda->getCache('evenements')) || count($eqLogic_agenda->getCache('evenements')) == 0) {
      return $return;
    }

    if ($jour == "aujourdhui"){
      $starttime = strtotime('0 day 00:00:00');
      $endtime = strtotime('0 day 23:59:59');
    }
    
    if ($jour == "demain"){
      $starttime = strtotime('+1 day 00:00:00');
      $endtime = strtotime('+1 day 23:59:59');
    }
    if ($jour == "hier"){
      $starttime = strtotime('-1 day 00:00:00');
      $endtime = strtotime('-1 day 23:59:59');
    }

    $return=[];
    $return["debut"]=PHP_INT_MAX;
    $return["fin"]=0;
    $return["Jour_entier"]=0;
    foreach ($eqLogic_agenda->getCache('evenements') as $evenement) {
      $endtime_event = strtotime($evenement['Fin']);
      if ($endtime_event == $starttime && strtotime($evenement['Debut']) <> $starttime) {//Si fin à 00:00 et que ce n'est pas un évènement "instantané" alors on mets la fin à 23:59:59
        $endtime_event = $endtime_event - 1;
      }
      //self::add_log("debug", strtotime($evenement['Debut']) . "<=". $endtime,$this);
      //self::add_log("debug", $endtime_event . ">=" . $starttime,$this);
      if ($jour =="aujourdhui" && strtotime($evenement['Debut']) < $starttime ){
       //return "";
      }
      if ($jour =="demain" && strtotime($evenement['Debut']) < $starttime ){
       //return "";
      }
      if (strtotime($evenement['Debut']) <= $endtime && $endtime_event >= $starttime) {

        $recherche_dans = '';
        if ($sur_titre == 1){
          $recherche_dans .= $evenement['Nom_évènement'];

        }
        if ($sur_contenu == 1){
          $recherche_dans .= $evenement['Description'];
        }
        if(strpos($filtre, "|")!== false){
          //self::add_log("debug","Multifiltre.". $filtre,$this);
          $arr_filtre=explode("|",$filtre);
          for ($i = 0; $i < count($arr_filtre); $i++) {
            if(strpos(strtolower($recherche_dans), strtolower($arr_filtre[$i]))!== false){
              $trouve=true;
              if ($return["debut"]>strtotime($evenement['Debut'])){
                $return["debut"] = strtotime($evenement['Debut']);
              }
              if ($return["fin"]<$endtime_event){
                $return["fin"] = $endtime_event;
              }
              $return["Jour_entier"] = $evenement['Jour_entier'];
            } 
          }
        }elseif ($filtre ==  "*"){
          $trouve=true;
          if ($return["debut"]>strtotime($evenement['Debut'])){
            $return["debut"] = strtotime($evenement['Debut']);
          }
          if ($return["fin"]<$endtime_event){
            $return["fin"] = $endtime_event;
          }
          $return["Jour_entier"] = $evenement['Jour_entier'];
        }else{

          if(strpos(strtolower($recherche_dans), strtolower($filtre))!== false){
            $trouve=true;
            if ($return["debut"]>strtotime($evenement['Debut'])){
              $return["debut"] = strtotime($evenement['Debut']);
            }
            if ($return["fin"]<$endtime_event){
              $return["fin"] = $endtime_event;
            }
            $return["Jour_entier"] = $evenement['Jour_entier'];
          }
        }
      }
    }
    return $return;
  }

  public static function ajout_filtre($name) {
    $eqLogic = new eqLogic();
    $eqLogic->setEqType_name('google_agenda');
    $eqLogic->setName($name);
    $eqLogic->setLogicalId($name);
    $eqLogic->setConfiguration('type_equipement', 'filtre');
    $eqLogic->setConfiguration('agendas', []);		
    $eqLogic->save();
  }

  public static function ajout_agenda($name) {
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
        $cmd->setName(__('Prochain évènement du jour', __FILE__));
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
      //$this->setConfiguration("agendas",'[]');
      $this->Synchronisation_google();
    }else{
	  $cmd = $this->getCmd(null, 'maintenant');
      if (!is_object($cmd)) {
        $cmd = new google_agendaCmd();
        $cmd->setLogicalId('maintenant');
        $cmd->setName('Evènements en cours');
        $cmd->setEqLogic_id($this->getId());
        $cmd->setType('info');
        $cmd->setSubType('binary');
        $cmd->save();
        $this->checkAndUpdateCmd('aujourdhui', 0);
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

      $cmd = $this->getCmd(null, 'demain');
      if (!is_object($cmd)) {
        $cmd = new google_agendaCmd();
        $cmd->setLogicalId('demain');
        $cmd->setName('Evenement demain');
        $cmd->setEqLogic_id($this->getId());
        $cmd->setType('info');
        $cmd->setSubType('binary');
        $cmd->save();
        $this->checkAndUpdateCmd('demain', 0);

      }

      $cmd = $this->getCmd(null, 'hier');
      if (!is_object($cmd)) {
        $cmd = new google_agendaCmd();
        $cmd->setLogicalId('hier');
        $cmd->setName('Evenement hier');
        $cmd->setEqLogic_id($this->getId());
        $cmd->setType('info');
        $cmd->setSubType('binary');
        $cmd->save();
        $this->checkAndUpdateCmd('hier', 0);
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
	  $cmd = $this->getCmd(null, 'Commande_debut');
      if (!is_object($cmd)) {
        $cmd = new google_agendaCmd();
        $cmd->setLogicalId('Commande_debut');
        $cmd->setIsVisible(1);
        $cmd->setName(__('Commandes de début exécutées', __FILE__));
        $cmd->setType('info');
        $cmd->setSubType('binary');
        $cmd->setEqLogic_id($this->getId());
        $cmd->save();
        $this->checkAndUpdateCmd('Commande_debut', 0);
      }
      //eqLogic::byId($this->getConfiguration("equipement"))->Synchronisation_google();
      self::add_log("debug","Enregistrement filtre",$this);
      $this->recup_filtre(true);
    }


  }



}

class google_agendaCmd extends cmd {
  public function execute($_options = array()) {
    if ($this->getLogicalId() == 'refresh') {
      

      $eqLogic = $this->getEqLogic();
      google_agenda::add_log("debug", "********************************************************************",$eqLogic);
      google_agenda::add_log("debug","Rafraichir " . $this->getEqLogic()->getName(),$eqLogic);
      if ($eqLogic->getConfiguration("type_equipement") =="agenda"){
        //google_agenda::add_log("debug","Nom équipement:" . $eqLogic->getHumanName(),$eqLogic);
        $eqLogic->Synchronisation_google();
      }
      if ($eqLogic->getConfiguration("type_equipement") =="filtre"){
        google_agenda::add_log("debug","Nom équipement:" . $eqLogic->getHumanName(),$eqLogic);
        $eqLogic->recup_filtre(true);
      }
      $this->getEqLogic()->refreshWidget();
      //$this->getEqLogic()->refresh();
      google_agenda::add_log("debug","Fin Rafraichir " . $this->getEqLogic()->getName(),$eqLogic);
    }
  }

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