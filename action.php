<?php
/**
 * DokuWiki Plugin Addressbook
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author: Gero Gothe <gero.gothe@medizindoku.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
define('DEBUG',false);

/**
 * Class action_plugin_addressbook
 */
class action_plugin_addressbook extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) { $controller->register_hook('FORM_SEARCH_OUTPUT', 'AFTER', $this,'addSearchResults');}


    public function addSearchResults(Doku_Event $event, $param)
    {
        global $INPUT;
        
        $searchForm = $event->data;
  
        $list = $this->searchResult($_REQUEST['q']);
        
        if (!$list) return;
  
  
        $res .= '<div class="plugin_addressbook_searchpage">';
        $res .= '<h2>Results in Addressbook</h2>';
        
        $syntax = plugin_load('syntax', 'addressbook');
        
        
        if (count($list)<5) {
            foreach ($list as $l) $res .= $syntax->showcontact($l['id'],($this->getConf('search link target') != ''? $this->getConf('search link target'):false));
        } else $res .= $syntax->buildIndex($list,false,($this->getConf('search link target') != ''? $this->getConf('search link target'):false));
        
        
        
        foreach ($found as $f) $res .= $syntax->showcontact($f['id']);
        
        $res .= '</div>';

        $searchForm->addHTML($res);
    }
    
    
    /* Maximum 20 results ! */
    function searchResult ($text=false,$order='surname,firstname,cfunction'){
        if ($text == false || strlen($text) < 2) return false;
        
         try {
            $db_helper = plugin_load('helper', 'addressbook_db');
            $sqlite = $db_helper->getDB();
        } catch (Exception $e) {
            msg($e->getMessage(), -1);
            return false;
        }
        
        $text = explode(" ",$text);
        
        $sql = "select * from addresslist WHERE instr(lower(surname || firstname || tel1 || tel2 || fax || description || cfunction || department) ,'".$text[0]."') > 0";        
        for ($c=1;$c<count($text);$c++) $sql .= " AND instr(lower(' ' || surname || firstname || tel1 || tel2 || fax || description || cfunction || department) ,'".$text[$c]."') > 0";
        $sql .= " ORDER BY $order LIMIT 20";
        
        $query = $sqlite->query($sql);
        $res = $sqlite->res2arr($query);
        
        if ($sqlite->res2count($sqlite->query($sql)) == 0) {
            return false;
        }
        
        return $res;
    }

    
}

// vim:ts=4:sw=4:et:
