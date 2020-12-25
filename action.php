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
 * Class action_plugin_searchform
 */
class action_plugin_addressbook extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

        #$controller->register_hook('SEARCH_RESULT_PAGELOOKUP', 'BEFORE', $this,'showContactQuery');
         #$controller->register_hook('TPL_CONTENT_DISPLAYP', 'BEFORE', $this, 'showContactQuery',array());
         #$controller->register_hook('SEARCH_RESULT_FULLPAGE', 'BEFORE', $this, 'fullpage',array());
    }
    
    function showContactQuery(Doku_Event $event, $param){
        #print_r($data);
         #$event->data['listItemContent'][] = "Hallo";
    }
    
    

    
}

// vim:ts=4:sw=4:et:
