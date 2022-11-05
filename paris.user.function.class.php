<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
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
require_once dirname(__FILE__) . '/../../core/php/core.inc.php';

class userFunction {

    public static function plop($_arg1 = '') {
        log::add('user_function', 'error', 'arg1 : ' .$_arg1);
        return 'Argument 1 : ' . $_arg1;
    }

    public static function getIndiceConfortText($_indiceConfortKey = 0) {
        $indiceGlobalTab = array(
            0 => "Sain",
            1 => "Agréable",
            2 => "Acceptable",
            3 => "Médiocre",
            4 => "Mauvais",
        );

        return $indiceGlobalTab[$_indiceConfortKey];
    }

    public static function getAQIText($_aqiKey = 0) {
        $indiceGlobalTab = array(
            1 => "Bon",
            2 => "Correct",
            3 => "Dégradé",
            4 => "Mauvais",
            5 => "Très mauvais",
            6 => "Extrême",
        );

        return $indiceGlobalTab[$_aqiKey];
    }
  
    public static function getKeyfobButtonText($_scene = 0) {
        $indiceGlobalTab = array(
            10 => "Ouvrir",
            20 => "Fermer",
            23 => "Protéger",
            40 => "Alarme on",
            30 => "Alarme off",
            0 => "?",
        );

        return $indiceGlobalTab[$_scene];
    }
  
    public static function getDoorStatus($_infoPorte, $_infoSerrure, $_format) {
        $lockStatusCmd = cmd::byString($_infoSerrure);
        $lockStatusValue = $lockStatusCmd->execCmd();
        $dtlockStatusValueDate = DateTime::createFromFormat('Y-m-d H:i:s', $lockStatusCmd->getValueDate());
        $lockStatusValueDate = $dtlockStatusValueDate->format($_format);

        $openStatusCmd = cmd::byString($_infoPorte);
        $openStatusValue = $openStatusCmd->execCmd();
        $dtopenStatusValueDate = DateTime::createFromFormat('Y-m-d H:i:s', $openStatusCmd->getValueDate()) ;
        $openStatusValueDate = $dtopenStatusValueDate->format($_format);

        if ($lockStatusValue == 1) {
            $doorStatus = "verrouillée depuis le " . $lockStatusValueDate;
        } else {
            $doorStatus = ($openStatusValue == 1 ? "ouverte" : "fermée") . " depuis le " . $openStatusValueDate;
        }
      
        return $doorStatus;
    }  

    public static function getDistanceToHome($_device, $_nom, $_latitudeDom, $_longitudeDom) {
        $earthRadius = '6371000' ; // rayon terrestre
        $cmd_position = '#[communication][' . $_device . ' ' . $_nom . '][Position]#' ;
        $coord_gps_position = explode("," , cmd::byString($cmd_position)->execCmd() ); //coordonnées GPS actuelles
        $coord_gps_positionDom = explode("," , $_latitudeDom . "," . $_longitudeDom); //coordonnées GPS domicile

        $latTo = deg2rad($coord_gps_position[0]);
        $lonTo = deg2rad($coord_gps_position[1]);
        $latDom = deg2rad($coord_gps_positionDom[0]);
        $lonDom = deg2rad($coord_gps_positionDom[1]);
        // $msg = "coordonnéee gps " . "[". $coord_gps_position[0] . ", ". $coord_gps_position[1] . "]" . "[" . $coord_gps_positionDom[0] . ", " . $coord_gps_positionDom[1] . "]" ;
        // log::add('user_function','error',$msg);

        $lonDelta = $lonDom - $lonTo;
        
        $a = pow(cos($latDom) * sin($lonDelta), 2) + pow(cos($latTo) * sin($latDom) - sin($latTo) * cos($latDom) * cos($lonDelta), 2);
        $b = sin($latTo) * sin($latDom) + cos($latTo) * cos($latDom) * cos($lonDelta);
        $angle = atan2(sqrt($a), $b);
        $distfromdom = $angle * $earthRadius / 1000 ;
        if ( $distfromdom <= 10 ) $distfromdom = round($distfromdom,1) ;
        else $distfromdom = round($distfromdom,0) ;

        return $distfromdom;
    }

    public static function getCmdProperty($_cmdString, $_cmdProperty) {
//          log::add('user_function', 'error', $_cmdString . ' ' . $_cmdProperty);

        $propertyValue = 'error in getting value';
        $cmd = cmd::byString($_cmdString);
        if( $_cmdProperty == 'IsVisible' )
            $propertyValue = $cmd->getIsVisible();
        if ($_cmdProperty == 'IsHistorized' )
            $propertyValue = $cmd->getIsHistorized();
      
      return $propertyValue;
    }

    public static function setCmdProperty($_cmdString, $_cmdProperty, $_propertyValue) {
//          log::add('user_function', 'error', $_cmdString . ' ' . $_cmdProperty . ' ' . $_propertyValue);

        $cmd = cmd::byString($_cmdString);
        if( $_cmdProperty == 'IsVisible' )
            $cmd->setIsVisible($_propertyValue);
        if ($_cmdProperty == 'IsHistorized' )
            $cmd->setIsHistorized($_propertyValue);
        $cmd->save();
      
        return $_propertyValue;
    }

    public static function askMe($_title, $_question, $_possibleAnswers, $_timeout, $_commandAskName) {

    // créer une variable temporaire pour le ask
    $variable = 'Ask Temporaire';
    $dataStore = dataStore::byTypeLinkIdKey('scenario', -1, $variable);
    if (!is_object($dataStore)) {
        $dataStore = new dataStore();
        $dataStore->setKey($variable);
        $dataStore->setType('scenario');
        $dataStore->setLink_id(-1);                                  
    }   
    $dataStore->setValue('');
    $dataStore->save(); 

    // exécution de la commande ask
    $options_cmd = array(
        'title' => $_title, 
        'message' => $_question,
        'answer' => explode(';', $_possibleAnswers), 
        'timeout' => $_timeout, 
        'variable' =>$variable,
    );
    $cmd = cmd::byString($_commandAskName);
    $cmd->setCache('ask::variable', $variable);
    $cmd->setCache('ask::endtime', strtotime('now') + $_timeout);
    $cmd->setCache('ask::answer', $options_cmd['answer']);
    $cmd->execCmd($options_cmd);

    // attente de la réponse ou de l'épuisement du délai
    $occurence = 0;
    $value = '';
    while (true) {
        $dataStore = dataStore::byTypeLinkIdKey('scenario', -1, $variable);
        if (is_object($dataStore)) {
            $value = $dataStore->getValue();
        };                         
        if ($value != '' or $occurence > $_timeout) {
            break;
        };
        $occurence++;
        sleep(1);
    };

    // supprimer la variable et retourne la réponse
        $dataStore = dataStore::byTypeLinkIdKey('scenario', -1, $variable);
        $dataStore->remove();

    return $value;

    }
}