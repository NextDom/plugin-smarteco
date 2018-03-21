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
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class smarteco extends eqLogic
{

    public static function health()
    {
        $adresseIPServeur     = config::byKey('adresseIP', 'smarteco');
        $port                 = 80;
        $waitTimeoutInSeconds = 1;

        $return[] = array(
            'test'   => __('IP Module Serveur', __FILE__),
            'result' => ($adresseIPServeur) ? __('OK', __FILE__) : __('NOK', __FILE__),
            'advice' => ($adresseIPServeur) ? '' : __('L\'adresse IP de votre module serveur n\'est pas renseignée', __FILE__),
            'state'  => $adresseIPServeur,
        );


        if (isset($adresseIPServeur)) {
            $fp = fsockopen($adresseIPServeur, $port, $errCode, $errStr, $waitTimeoutInSeconds);
            fclose($fp);


            $return[] = array(
                'test'   => __('Ping Module Serveur', __FILE__),
                'result' => ($fp) ? __('OK', __FILE__) : __('NOK', __FILE__),
                'advice' => ($fp) ? '' : __('L\'adresse IP de votre module serveur ne répond pas, vérifiez qu\'elle n\'a pas changée', __FILE__),
                'state'  => $fp,
            );
        }

        return $return;
    }

    public function syncWithSmartEco()
    {
        $adresseIPServeur = config::byKey('adresseIP', 'smarteco');
        //API Url
        $url              = 'http://' . $adresseIPServeur . '/xml/liste-pieces.xml';

        log::add('smarteco', 'debug', 'Contacting ' . print_r($url, true) . '...');

        //Initiate cURL.
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

        $data = curl_exec($ch);
        curl_close($ch);

        $response = new SimpleXMLElement($data, LIBXML_NOCDATA);
        $json     = json_encode($response);
        $array    = json_decode($json, TRUE);

        log::add('smarteco', 'debug', print_r($json, true));
        //print_r($response);
        //exit;
        // création de l'équipement module serveur 
        $eqLogic = eqLogic::byLogicalId('module_serveur', 'smarteco');
        if (!is_object($eqLogic) && $thermostat != 'NULL') {
            $eqLogic = new smarteco();
            $eqLogic->setEqType_name('smarteco');
            $eqLogic->setIsEnable(1);
            $eqLogic->setName('Module serveur');
            $eqLogic->setLogicalId('module_serveur');
            $eqLogic->setCategory('heating', 1);
            $eqLogic->setIsVisible(0);
            $eqLogic->save();
            log::add('smarteco', 'debug', 'Création module serveur...');


            // Création des commandes infos
            self::createCommand($eqLogic, 'On / Off', 'info', 'binary', 'On/Off', 1, '', $order + 1, '', '', 1, null, null);
            self::createCommand($eqLogic, 'premiere_connexion', 'info', 'binary', 'Première connexion', 1, '', $order + 1, '', '', 1, null, null);
            self::createCommand($eqLogic, 'init_en_cours', 'info', 'binary', 'Initialisation en cours', 1, '', 2, null, null);
            self::createCommand($eqLogic, 'rf_present', 'info', 'numeric', 'RF Présent', 0, '', 3, null, null);
            self::createCommand($eqLogic, 'etat_wifi', 'info', 'numeric', 'Etat Wifi', 1, '', 4, null, null);
            self::createCommand($eqLogic, 'etat_tic', 'info', 'numeric', 'Etat TIC', 0, '', 5, null, null);
            self::createCommand($eqLogic, 'tarif_avec_pointe', 'info', 'string', 'Tarif avec pointe', 0, '', 6, null, null);
            self::createCommand($eqLogic, 'autre_equipement', 'info', 'string', 'Autre équipement', 0, '', 7, null, null);
            self::createCommand($eqLogic, 'connect_internet', 'info', 'numeric', 'Connexion Internet', 0, '', 8, null, null);
            self::createCommand($eqLogic, 'maj_ok', 'info', 'numeric', 'Maj OK', 0, '', 9, null, null);
            self::createCommand($eqLogic, 'v_interface', 'info', 'string', 'Version Interface', 0, '', 10, null, null);
            self::createCommand($eqLogic, 'v_app', 'info', 'string', 'Version App', 0, '', 11, null, null);
            self::createCommand($eqLogic, 'refreshStatus', 'action', 'other', 'Refresh', 1, '', 16, null, null);
        }


        // Création des pièces 
        $pieces = $array['liste_pieces']['piece'];
        foreach ($pieces as $piece) {
            $idp        = $piece['@attributes']['id'];
            $nomPiece   = ucfirst(strtolower($piece['nom_piece']));
            $thermostat = $piece['thermostats'];

            $eqLogic = eqLogic::byLogicalId($idp, 'smarteco');
            if (!is_object($eqLogic) && $thermostat != 'NULL') {
                $eqLogic = new smarteco();
                $eqLogic->setEqType_name('smarteco');
                $eqLogic->setIsEnable(1);
                $eqLogic->setName($nomPiece);
                $eqLogic->setLogicalId($idp);
                $eqLogic->setCategory('heating', 1);
                $eqLogic->setIsVisible(1);


                $pieceParent = object::byName($nomPiece);
                if (isset($pieceParent)) {
                    $eqLogic->setObject_id($pieceParent->getId());
                }

                $eqLogic->save();
                log::add('smarteco', 'debug', 'Création radiateur(s) pièce ID ' . print_r('ID ' . $idp, true) . ' ' . print_r($nomPiece, true) . ' ...');


                // CRéation des commandes infos
                self::createCommand($eqLogic, 'OnOffState', 'info', 'binary', 'On/Off', 1, '', 1, '', '', 1, null, null);
                self::createCommand($eqLogic, 'mode_piece', 'info', 'string', 'Mode Pièce', 1, '', 2, '', '', 0, null, null);
                self::createCommand($eqLogic, 'allure_piece', 'info', 'string', 'Allure Pièce', 1, '', 3, '', '', 0, null, null);
                self::createCommand($eqLogic, 'consigne_preferee', 'info', 'numeric', 'Consigne Préférée', 0, '', 4, '', '', 0, null, null);
                self::createCommand($eqLogic, 'consigne_piece', 'info', 'numeric', 'Consigne Pièce', 1, '', 5, '', '', 0, null, 'THERMOSTAT_SETPOINT');
                self::createCommand($eqLogic, 'prog_pieceState', 'info', 'numeric', 'Calendrier de chauffe', 0, '', 6, '', '', 0, null, null);
                self::createCommand($eqLogic, 'prog_piece', 'info', 'numeric', 'ID calendrier de chauffe', 0, '', 7, '', '', 0, null, null);
                self::createCommand($eqLogic, 'encardrement_temp_plus', 'info', 'numeric', 'Encadrement Temp Plus', 0, '', 8, '', '', 0, null, null);
                self::createCommand($eqLogic, 'encardrement_temp_moins', 'info', 'numeric', 'Encadrement Temp Moins', 0, '', 9, '', '', 0, null, null);
                self::createCommand($eqLogic, 'fenetre_piece', 'info', 'numeric', 'Fenetre Pièce', 0, '', 10, '', '', 0, null, null);
                self::createCommand($eqLogic, 'presence_piece', 'info', 'numeric', 'Presence Pièce', 0, '', 11, '', '', 0, null, null);
                self::createCommand($eqLogic, 'verrou_piece', 'info', 'numeric', 'Verrou Pièce', 0, '', 12, '', '', 0, null, null);
                self::createCommand($eqLogic, 'derog_piece', 'info', 'numeric', 'Derog Pièce', 0, '', 13, '', '', 0, null, null);
                self::createCommand($eqLogic, 'allure_derog', 'info', 'numeric', 'Allure Derog', 0, '', 13, '', '', 0, null, null);
                self::createCommand($eqLogic, 'heure_debut_derog', 'info', 'numeric', 'Heure Debut Derog', 0, '', 14, '', '', 0, null, null);
                self::createCommand($eqLogic, 'heure_fin_derog', 'info', 'numeric', 'Heure Fin Derog', 0, '', 15, '', '', 0, null, null);

                // ations :
                // Refresh
                self::createCommand($eqLogic, 'refreshStatus', 'action', 'other', 'Refresh', 1, '', 16, '', '', 0, 'fa-refresh', null);

                // Mode
                self::createCommand($eqLogic, 'setOperationModeHG', 'action', 'other', 'Hors Gel', 1, '', 17, '', '', 0, 'nature-snowflake', null);
                self::createCommand($eqLogic, 'setOperationModeDerogConfort', 'action', 'other', 'Mode derog Confort', 1, '', 18, '', '', 0, 'fa_clock-o', null);
                self::createCommand($eqLogic, 'setOperationModeDerogEco', 'action', 'other', 'Mode derog Eco', 1, '', 19, '', '', 0, 'fa_clock-o', null);
                self::createCommand($eqLogic, 'setHeatingLvlUp', 'action', 'other', 'Temp up', 1, '', 19, '', '', 0, 'fa-plus', null);
                self::createCommand($eqLogic, 'setHeatingLvlDown', 'action', 'other', 'Temp down', 1, '', 19, '', '', 0, 'fa-minus', null);
                self::createCommand($eqLogic, 'setHeatingLvlEco', 'action', 'other', 'Eco', 0, '', 20, '', '', 0, 'fa-moon-o', null);
                self::createCommand($eqLogic, 'setHeatingLvlComfort', 'action', 'other', 'Confort', 0, '', 21, '', '', 0, 'meteo-soleil', null);
                self::createCommand($eqLogic, 'cancelDerog', 'action', 'other', 'Fin Derog', 1, '', 24, '', '', 0, 'fa-stop', null);
            }
        }
        self::refresh_all();
    }

    protected function createCommand($eqLogic, $cmdId, $type, $subType, $name, $visible = 1, $value = '', $order = 999, $dashboard = '', $mobile = '', $isHistorized = 0, $icon = null, $generic_type = null)
    {
        $cmd = $eqLogic->getCmd($type, $cmdId);
        if (!is_object($cmd)) {
            $cmd = new smartecoCmd();
        }

        $cmd->setEqLogic_id($eqLogic->getId());
        $cmd->setLogicalId($cmdId);
        $cmd->setType($type);
        $cmd->setSubType($subType);
        $cmd->setName($name);
        $cmd->setIsVisible($visible);
        $cmd->setIsHistorized($isHistorized);
        $cmd->setOrder($order);

        if (isset($icon)) {
            $cmd->setDisplay('icon', '<i class="fa ' . $icon . '"></i>');
            //log::add('smarteco', 'debug', 'AJout icone ' . print_r($icon, true) . ' dans ' . print_r($name, true)  .' ...');
        }
        if (isset($generic_type)) {
            $cmd->setDisplay('generic_type', $generic_type);
            //log::add('smarteco', 'debug', 'AJout type générique ' . print_r($generic_type, true) . ' dans ' . print_r($name, true)  .' ...');
        }

        if ($dashboard != '') {
            $cmd->setTemplate('dashboard', $dashboard);
        } else {
            $cmd->setTemplate('dashboard', 'default');
        }

        if ($mobile != '') {
            $cmd->setTemplate('mobile', $mobile);
        } else {
            $cmd->setTemplate('mobile', 'default');
        }

        $cmd->save();
        if ($value != '') {
            $cmd->setValue($value);
            $cmd->event($value);
        }
    }

    /*    

    public function preInsert()
    {
        
    }

    public function postInsert()
    {
        
    }

    public function preSave()
    {
        
    }

    public function postSave()
    {
        
    }

    public function preUpdate()
    {
        
    }

    public function postUpdate()
    {
        
    }

    public function preRemove()
    {
        
    }

    public function postRemove()
    {
        
    }*/

    public function updateCmd($commandName, $newValue)
    {
        $cmd = $this->getCmd('info', $commandName);
        if (is_object($cmd) && $cmd->execCmd() !== $cmd->formatValue($newValue)) {
            $cmd->setCollectDate('');
            $cmd->event($newValue);
        }
    }

    public function refreshStatus()
    {
        log::add('smarteco', 'debug', 'Starting refresh status');
        try {

            $eqName           = $this->getLogicalId();
            $adresseIPServeur = config::byKey('adresseIP', 'smarteco');

            if ($eqName == "module_serveur") {
                //API Url
                $url = 'http://' . $adresseIPServeur . '/xml/status-general.xml';

                log::add('smarteco', 'debug', 'Contacting ' . print_r($url, true) . '...');

                $ch = curl_init($url);

                curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

                if (!$data = curl_exec($ch)) {
                    throw new \Exception(curl_error($ch));
                }
                curl_close($ch);

                $response = new SimpleXMLElement($data, LIBXML_NOCDATA);
                $json     = json_encode($response);
                $array    = json_decode($json, TRUE);

                log::add('smarteco', 'debug', print_r($json, true));

                foreach ($array as $key => $value) {
                    $cmd    = $this->getCmd('info', $key);
                    $target = $value;

                    if (is_object($cmd) && $cmd->execCmd() !== $cmd->formatValue($target)) {
                        //log::add('smarteco', 'debug', 'Saving New values : (' . print_r($key, true) . ' : ' . print_r($value, true) .')');
                        $cmd->setCollectDate('');
                        $cmd->event($target);
                    }
                }
            } else {
                //API Url
                $url = 'http://' . $adresseIPServeur . '/mon-logement.html?n_piece=' . $this->getLogicalId();

                log::add('smarteco', 'debug', 'Contacting ' . print_r($url, true) . '...');

                //Initiate cURL.
                $ch = curl_init($url);

                curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

                if (!$piece = curl_exec($ch)) {
                    throw new Exception(curl_error($ch));
                }
                curl_close($ch);

                //API Url
                $url = 'http://' . $adresseIPServeur . '/xml/status-piece.xml';

                log::add('smarteco', 'debug', 'Contacting ' . print_r($url, true) . '...');

                //Initiate cURL.
                $ch = curl_init($url);

                curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

                if (!$data = curl_exec($ch)) {
                    throw new \Exception(curl_error($ch));
                }
                curl_close($ch);

                $response = new SimpleXMLElement($data, LIBXML_NOCDATA);
                $json     = json_encode($response);
                $array    = json_decode($json, TRUE);

                log::add('smarteco', 'debug', print_r($json, true));

                $interpretationResultat = array(
                    "mode_piece"   => array("0" => "Manuel", "1" => "Manuel", "2" => "Manuel", "3" => "Manuel", "4" => "Arrêt", "5" => "Manuel", "6" => "Manuel", "7" => "Auto", "8" => "Prog. interne"),
                    "allure_piece" => array("0" => "Economie", "1" => "Confort -2", "2" => "Confort -1", "3" => "Confort", "4" => "Arrêt", "5" => "Hors-gel", "6" => "Confort plus", "7" => "Auto"),
                    "allure_derog" => array("0" => "Economie", "3" => "Confort", "5" => "Hors-gel")
                );

                foreach ($array['reponse_piece'] as $key => $value) {
                    $cmd    = $this->getCmd('info', $key);
                    $target = array_key_exists($key, $interpretationResultat) ? $interpretationResultat[$key][$value] : $value;

                    if (is_object($cmd) && $cmd->execCmd() !== $cmd->formatValue($target)) {
                        //log::add('smarteco', 'debug', 'Saving New values : (' . print_r($key, true) . ' : ' . print_r($value, true) .')');
                        $cmd->setCollectDate('');
                        $cmd->event($target);
                    }
                }
                // calcul et interpretations
                //on / off
                $cmd        = $this->getCmd('info', 'OnOffState');
                $onOffState = $array['reponse_piece']['mode_piece'] == "4" ? "0" : "1";
                if (is_object($cmd) && $cmd->execCmd() !== $cmd->formatValue($onOffState)) {
                    $cmd->setCollectDate('');
                    $cmd->event($onOffState);
                }

                // Calendrier de chauffe
                $cmd            = $this->getCmd('info', 'prog_pieceState');
                $progPieceState = $array['reponse_piece']['prog_piece'] == "0" ? "0" : "1";
                if (is_object($cmd) && $cmd->execCmd() !== $cmd->formatValue($progPieceState)) {
                    $cmd->setCollectDate('');
                    $cmd->event($value);
                }

                // Derog en cours
                if ($array['reponse_piece']['derog_piece'] == 1 && $array['reponse_piece']['mode_piece'] != 4) {
                    $this->updateCmd("mode_piece", "Derog");
                }
                log::add('smarteco', 'debug', 'End refresh status');
                return 1;
            }
        } catch (\Exception $e) {

            log::add('smarteco', 'debug', print_r($e->getMessage(), true));

            $cmd = $this->getCmd('info', 'OnOffState');
            if (is_object($cmd) && $cmd->execCmd() !== $cmd->formatValue(0)) {
                $cmd->setCollectDate('');
                $cmd->event(0);
            }

            $cmd = $this->getCmd('info', 'mode_piece');
            if (is_object($cmd) && $cmd->execCmd() !== $cmd->formatValue('Déconnecté')) {
                $cmd->setCollectDate('');
                $cmd->event('Déconnecté');
            }
        }
    }

    public function getImage()
    {
        if ($this->getLogicalId() == "module_serveur") {
            return 'plugins/smarteco/core/img/module_serveur.png';
        } else {
            return 'plugins/smarteco/core/img/chauffage.png';
        }
    }

    public static function refresh_all()
    {
        log::add('smarteco', 'debug', 'Starting refresh all');
        try {

            $eqs = eqLogic::byType('smarteco', true);

            foreach ($eqs as $eq) {
                $eq->refreshStatus();
            }
            return 1;
        } catch (Exception $e) {
            log::add('smarteco', 'debug', print_r($e, true));
        }
    }

    public static function cron5()
    {
        self::refresh_all();
    }

    public static function setOperationMode(&$eqLogic, $piece, $mode)
    {
        log::add('smarteco', 'debug', 'Starting setOperationMode avec mode = "' . $mode . '" pour la piece ' . $piece);
        try {
            $adresseIPServeur = config::byKey('adresseIP', 'smarteco');


            //API Url
            $url = 'http://' . $adresseIPServeur . '/mon-logement.html?n_piece=' . $piece;

            log::add('smarteco', 'debug', 'Contacting ' . print_r($url, true) . '...');

            $Heures15  = date("H") * 4; // 64
            $Minutes15 = floor(date("i") / 15); // 2

            $heureDebut = $Heures15 + $Minutes15 - 1;
            $heureFin   = 90;

            switch ($mode) {
                case "Eco":
                    $allureDerog = '0';
                    break;
                case "Confort":
                    $allureDerog = '3';
                    break;
                case "HG":
                    $allureDerog = '5';
                    break;
                case "cancel":
                    $piece       = 0;
                    $allureDerog = 0;
                    $heureDebut  = 0;
                    $heureFin    = 0;
            }

            //Initiate cURL.
            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

            curl_exec($ch);

            //API Url
            $url = 'http://' . $adresseIPServeur . '/cgi/derog_piece.cgi?pieceDerog=' . $piece . '&allureDerog=' . $allureDerog . '&heureDebutDerog=' . $heureDebut . '&heureFinDerog=' . $heureFin;

            log::add('smarteco', 'debug', 'Contacting ' . print_r($url, true) . '...');

            //Initiate cURL.
            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

            $data = curl_exec($ch);

            curl_close($ch);

            log::add('smarteco', 'debug', 'Résultat de la requete : ' . print_r($data, true) . '...');

            $eqLogic->refreshStatus();

            return 1;
        } catch (\Exception $e) {
            log::add('smarteco', 'debug', 'Erreur irratrapable setOperationMode ' . print_r($e));
        }
    }

    public static function setHeatingLevel(&$eqLogic, $piece, $increment)
    {
        log::add('smarteco', 'debug', 'Starting setHeatingLevel avec increment = "' . $increment . '" pour la piece ' . $piece);
        try {
            // il faut que les radiateurs soient en mode auto sinon ça plante....

            $cmd         = $eqLogic->getCmd('info', 'mode_piece');
            $modePiece   = $cmd->execCmd();
            $cmd         = $eqLogic->getCmd('info', 'allure_piece');
            $allurePiece = $cmd->execCmd();

            if ($modePiece != "Auto") {
                return 0;
            }
            if ($allurePiece != "Confort") {
                // on passe en derog confort pour appliquer la température
                $eqLogic->setOperationMode($eqLogic, $piece, 'Confort');
            }

            $adresseIPServeur = config::byKey('adresseIP', 'smarteco');
            $cmd              = $eqLogic->getCmd('info', 'consigne_piece');
            $baseTemperature  = $cmd->execCmd();

            $newTemperature = ($baseTemperature + $increment) * 10;

            log::add('smarteco', 'debug', 'New temp  ' . print_r($newTemperature / 10, true) . ' -> ' . $newTemperature);

            //API Url
            $url = 'http://' . $adresseIPServeur . '/mon-logement.html?n_piece=' . $piece;
            log::add('smarteco', 'debug', 'Contacting ' . print_r($url, true) . '...');

            //Initiate cURL.
            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

            curl_exec($ch);

            //API Url 
            $url = 'http://' . $adresseIPServeur . '/cgi/consigne_piece.cgi?newConsignePiece=' . $newTemperature;

            log::add('smarteco', 'debug', 'Contacting ' . print_r($url, true) . '...');

            //Initiate cURL.
            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

            $data = curl_exec($ch);

            curl_close($ch);

            log::add('smarteco', 'debug', 'Résultat de la requete : ' . print_r($data, true) . '...');

            // Enregistrement de la nouvelle temp.
            $cmd = $eqLogic->getCmd('info', 'consigne_piece');
            if (is_object($cmd) && $cmd->execCmd() !== $cmd->formatValue($newTemperature / 10)) {
                $cmd->setCollectDate('');
                $cmd->event($newTemperature / 10);
                log::add('smarteco', 'debug', 'Enregistremennt de la nouvelle températeure ' . print_r($newTemperature / 10, true) . '...');
            }

            return 1;
        } catch (\Exception $e) {
            log::add('smarteco', 'debug', 'Erreur irratrapable setHeatingLevel ' . print_r($e));
        }
    }

    public function toHtml($_version = 'dashboard')
    {
        $replace = $this->preToHtml($_version);
        if (!is_array($replace)) {
            return $replace;
        }
        $version = jeedom::versionAlias($_version);
        if ($this->getDisplay('hideOn' . $version) == 1) {
            return '';
        }
        foreach ($this->getCmd('info') as $cmd) {
            $replace['#' . $cmd->getLogicalId() . '_history#'] = '';
            $replace['#' . $cmd->getLogicalId() . '_id#']      = $cmd->getId();
            $replace['#' . $cmd->getLogicalId() . '#']         = $cmd->execCmd();
            $replace['#' . $cmd->getLogicalId() . '_collect#'] = $cmd->getCollectDate();
            if ($cmd->getLogicalId() == 'encours') {
                $replace['#thumbnail#'] = $cmd->getDisplay('icon');
            }
            if ($cmd->getIsHistorized() == 1) {
                $replace['#' . $cmd->getLogicalId() . '_history#'] = 'history cursor';
            }
        }
        foreach ($this->getCmd('action') as $cmd) {
            $replace['#' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
        }
        return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, 'smarteco', 'smarteco')));
    }

    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
      public function toHtml($_version = 'dashboard') {

      }
     */

}

class smartecoCmd extends cmd
{
    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

    public function execute($_options = array())
    {
        $eqLogic = $this->getEqLogic();
        $piece   = $eqLogic->getLogicalId();

        switch ($this->getLogicalId()) {
            case 'refreshStatus' :
                return $eqLogic->refreshStatus();
                break;

            case 'setOperationModeOff' :
                return $eqLogic->setOperationMode($eqLogic, $piece, 'HG');
                break;

            case 'setOperationModeDerogConfort' :
                return $eqLogic->setOperationMode($eqLogic, $piece, 'Confort');
                break;

            case 'setOperationModeDerogEco' :
                return $eqLogic->setOperationMode($eqLogic, $piece, 'Eco');
                break;

            case 'setHeatingLvlUp':
                return $eqLogic->setHeatingLevel($eqLogic, $piece, +0.1);
                break;

            case 'setHeatingLvlDown':
                return $eqLogic->setHeatingLevel($eqLogic, $piece, -0.1);
                break;

            case 'cancelDerog' :
                return $eqLogic->setOperationMode($eqLogic, $piece, "cancel");
                break;
        }
    }

}

            
