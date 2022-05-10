<?php

/**
 * Addressbook Plugin
 *
 * @license  GPL2
 * @author   Gero Gothe <gero.gothe@medizindoku.de>
 * 
 */
 
 // TS: 2022-04-04 - eigene Anpassung, um die Felder cfunction und tel2 als Straße und PLZ+Ort zu missbrauchen
 
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

define('ADRESSBOOK_SQL',false); # for development purposes

class syntax_plugin_addressbook extends DokuWiki_Syntax_Plugin {

    public $editor = false;   # Does user have editing rights for contacts?
    public $loggedin = false; # User logged in?
    
    function getSort(){
        return 158;
    }
    
    public function getType() {
        return 'substition';
    }

    /**
     * Paragraph Type
     */
    public function getPType() {
        return 'block';
    }
    
    
    function __construct(){
        global $INFO;
        if ($INFO['ismanager'] === true) $this->editor = true;
        if (isset($INFO['userinfo'])) $this->loggedin = true;
    }

    /**
     * @param string $mode
     */
    public function connectTo($mode) {$this->Lexer->addEntryPattern('\[ADDRESSBOOK.*?', $mode, 'plugin_addressbook');}
    
    public function postConnect() { $this->Lexer->addExitPattern('\]','plugin_addressbook'); }

    /**
     * Handler to prepare matched data for the rendering process
    */
    public function handle($match, $state, $pos, Doku_Handler $handler) {
        
        if ($state == DOKU_LEXER_UNMATCHED) {
            if ($match==':') return 'LOOKUP';
            if ($match[0]==':') $match = substr($match,1);
            
            return trim($match);
        }
        return false;
    }
    

    /**
     * @param   $mode     string        output format being rendered
     * @param   $renderer Doku_Renderer the current renderer object
     * @param   $data     array         data created by handler()
     * @return  boolean                 rendered correctly?
     */
	public function render($mode, Doku_Renderer $renderer, $data) {
        switch ($mode) {
            case "xhtml":
                /* @var Doku_Renderer_xhtml $renderer */
                // Calling the XHTML render function.
                return $this->render_for_xhtml($renderer, $data);
            case "odt":
                /* @var renderer_plugin_odt_page $renderer */
                // Calling the ODT render function.
                return $this->render_for_odt($renderer, $data);
        }
 
        // Success. We did support the format.
        return false;
    }
 
    /**
     * @param   $renderer Doku_Renderer the current renderer object
     * @param   $data     array         data created by handler()
     * @return  boolean                 rendered correctly?
     */
    protected function render_for_xhtml (&$renderer, $data) {
        // Generate XHTML content...
        global $ID;
        $renderer->info['cache'] = false;
				
		# addressbook_debug_show();
		
		if ($_REQUEST['Submit'] == $this->getLang('exec cancel')){
			unset($_REQUEST);
			unset ($cinfo);
			$action = $data;
		}
		
		# Main action given by tag data
		if (!isset($_REQUEST['Submit'])) $action = $data;


		/* Save a contact
		 * 
		 * On fail: show edit form again
		 */
		if ($_REQUEST['Submit']==$this->getLang('exec save')) $action = "savedata";
		
		# Certain actions could cause double saving, which is avoided by counting
		if ($action=='savedata' && $this->saveOnce == 0 && $this->editor){
			$this->saveOnce++;
			$contact_id = $_REQUEST['editcontact'];
			$cinfo = $this->loadFormData(); # Loads form data concerning the contact
			$res = $this->saveData($cinfo);
			if (!$res) {
				$action = 'edit';
				$contact_id = $_REQUEST['contactid'];
			} else { # Clear all
				unset($_REQUEST);
				unset ($cinfo);
				$action = $data;
			}
		}

		# Delete a contact
		if (isset($_REQUEST['erasecontact'])  && $this->editor){
			$this->deleteContact($_REQUEST['erasecontact']);
		}

		/* Directly show contact card by tag
		 * 
		 * Cards are only display, when there is NO edit or save action
		 */
		if (substr($data,0,7) == 'contact' && 
			!isset($_REQUEST['editcontact']) &&
			$action != 'edit'
			) {
			$renderer->doc .= $this->showcontact(intval(substr($data,8)),$ID);
			return; #no following actions
		}
		
	  
		if (substr($data,0,5) == 'index' &&
			!isset($_REQUEST['editcontact'])) {
			# showcontact once before if necessary
			if (isset($_REQUEST['showcontact']) && $this->showCount==0) {
				$this->showCount++;
				$out = $this->showcontact($_REQUEST['showcontact'],$ID);
				if ($out !== false) $renderer->doc .= $out.'<br>';
			}
			# now show index
			
			# keyword 'departments'
			if (strpos($data,'departments') > 0) {
				$list = $this->getList(false,'department,surname,firstname,cfunction');
				$renderer->doc .= $this->buildIndex($list,'department',$ID);
			} else $renderer->doc .= $this->buildIndex(false,false,$ID);
			
			return true; # no following actions
		}


		# --------------------------------------------------------#
		# only one instance beyond this point
		$this->instance++;
		if ($this->instance > 1) return;
		# --------------------------------------------------------#

		
		# Generate printable list
		if (substr($data,0,5) == 'print') {
			$pList = false;
			$sep = false;
			
			$params = $this->getValue($data,'');
			
			if (isset($params['department'])) $sep = 'department';
			
			# Select one department
			if (isset($params['select'])) {
				$pList = $this->getList(Array('department' => $params['select']));
				$sep = false;
			}
			 
			$renderer->doc .= $this->buildPrintList($pList,$sep);
			
			return true;
		}

		/* Edit contact or add a new contact
		 * 
		 * No futher actions are performed after an edit
		 */
		if ($action == 'addcontact' && $this->editor) { # Add a new contact. Can be overwritten by edit
			$contact_id = 'new';
			$action = 'edit'; # redefine action
		}
		
		if (isset($_REQUEST['editcontact'])  && $this->editor) { # Override new contact if the action is to edit an existing one
			$contact_id = $_REQUEST['editcontact'];
			$cinfo = $this->getContactData($contact_id);
			$action = 'edit';
		}
		
		if ($action == 'edit'  && $this->editor) {
			$out = $this->buildForm($contact_id,$cinfo);
			$renderer->doc .= $out;
			return; # no following actions
		}
		
		
		# Show search box
		if ($action == 'search' || $_REQUEST['Submit'] == $this->getLang('exec search')) {
			$out = $this->searchDialog();
			$renderer->doc .= $out;
		}
		
		
		if ($_REQUEST['Submit'] == $this->getLang('exec search')) {
			$list = $this->searchDB($_REQUEST['searchtext']);
			if ($list != false){
				if (count($list)<5) {
					foreach ($list as $l) $renderer->doc .= $this->showcontact($l['id'],$ID);
				} else $renderer->doc .= $this->buildIndex($list,false,$ID);
			}
		}

		
		/* Show contact per request
		 * can only be shown once due to the instance count above
		 * 
		 * placed below the searchbox
		 */            
		if (isset($_REQUEST['showcontact']) && $this->showCount==0) {
			$this->showCount++;
			$out = $this->showcontact($_REQUEST['showcontact'],$ID);
			if ($out !== false) $renderer->doc .= $out.'<br>';
		}

		return true;
    }
 
    /**
     * @param   $renderer Doku_Renderer the current renderer object
     * @param   $data     array         data created by handler()
     * @return  boolean                 rendered correctly?
     */
    protected function render_for_odt (&$renderer, $data) {
        global $ID;

        // Return if installed ODT plugin version is too old.
        if ( method_exists($renderer, 'getODTProperties') == false
            || method_exists($renderer, '_odtTableAddColumnUseProperties') == false
        ) {
            return false;
        }
		
		# Generate printable list
		if (substr($data,0,5) == 'print') {
			$pList = false;
			$sep = false;
			
			$params = $this->getValue($data,'');
			
			if (isset($params['department'])) $sep = 'department';
			
			# Select one department
			if (isset($params['select'])) {
				$pList = $this->getList(Array('department' => $params['select']));
				$sep = false;
			}
			 
			return $this->buildPrintList4ODT($renderer,$pList,$sep);
		}
		
		return false;
    }
    
    
    function searchDialog(){
        global $ID;
        $out = '';
        
        $out .= '<div class="plugin_addressbook_searchbox">';

        $out .= '<form enctype="multipart/form-data" action="'.wl($ID).'" method="POST">';
        $out .= '<span>'.$this->getLang('addressbook').'</span> <input type="text" name="searchtext" placeholder="'.$this->getLang('form search').'" value="'.$_REQUEST['searchtext'].'">';
        $out .= '<input type="submit" name="Submit" value="'.$this->getLang('exec search').'" />';
               
        $out .= '</form>';
        $out .= '</div>';
        
        return $out;
    }
    
    
    function buildForm($contact_id,$cinfo){
        global $ID;
        $out = '<div class="plugin_addressbook_editform">';
        
        if ($contact_id == 'new') {$out .= '<h2>'.$this->getLang('header add').'</h2>';} else {$out .= '<h2>'.$this->getLang('header edit').'</h2>';}
                
        $out .= '<br><form enctype="multipart/form-data" action="'.wl($ID).'" method="POST">';
        $out .= '<input type="hidden" name="MAX_FILE_SIZE" value="2200000" />';
        $out .= '<input type="hidden" name="contactid" value="'.$contact_id.'" />';
        $out .= '<input type="text" name="department" placeholder="'.$this->getLang('form department').'" value="'.$cinfo['department'].'">';
	    /* // das Feld firstname nutzen wir nicht: $out .= '<input type="text" name="firstname" placeholder="'.$this->getLang('form firstname').'" value="'.$cinfo['firstname'].'">';*/
        $out .= '<input type="text" name="surname" placeholder="'.$this->getLang('form surname').'" value="'.$cinfo['surname'].'"><br/>';
		$out .= '<input type="text" name="tel2" placeholder="'.$this->getLang('form tel2').'" value="'.$cinfo['tel2'].'">';
        $out .= '<input type="text" name="cfunction" placeholder="'.$this->getLang('form function').'" value="'.$cinfo['cfunction'].'"><br/>';
        $out .= '<input type="text" name="tel1" placeholder="'.$this->getLang('form tel1').'" value="'.$cinfo['tel1'].'">';
        $out .= '<input type="text" name="fax" placeholder="'.$this->getLang('form fax').'" value="'.$cinfo['fax'].'"><br>';
        $out .= '<input type="text" name="email" placeholder="'.$this->getLang('form email').'" value="'.$cinfo['email'].'"><br>';
        $out .= '<br>'.$this->getLang('form description').':<br><textarea name="description">'.$cinfo['description'].'</textarea><br><br>';
        
        $out .= '<div class="photoupload">';
        if (isset($_REQUEST['blob'])) $cinfo['photo'] = $_REQUEST['blob'];
        if ($cinfo['photo'] != false) {
            $out .= "<img style='float:left;max-width:120px' src='data:image/jpg;base64,".($cinfo['photo'])."'>";
            $out .= '<br><input type="checkbox" id="removephoto" name="removephoto" value="Remove photo"> ';
            $out .= '<label for="removephoto">'.$this->getLang('form remove').'</label><br><br>';
            $out .= $this->getLang('form upload info2').'.<br>';
            $out .= '<input type="hidden" name="blob" value="'.($cinfo['photo']).'">';
        }

        $out .= '</div>';
        $out .= $this->getLang('form upload').': <input name="photo" type="file" /> '.$this->getLang('form upload info').'.<br>';
        
        
        
        $out .= '<br><input type="submit" name="Submit" value="'.$this->getLang('exec save').'" />';
        $out .= '<input type="submit" name="Submit" value="'.$this->getLang('exec cancel').'" />';
        $out .= '</form>';
        $out .= "<div class='id'>ID: $contact_id</div>";
        $out .= '</div>';
        
        return $out;
    }
    
    function showcontact($cid,$target = false){
        
        $r = $this->getContactData($cid);

        if ($res === false) return false;
        
        $out ='';
        
        $out .= '<div class="plugin_addressbook_singlecontact">';
        
        $out .= '<div class="content">';
        
        $out .= '<div class="data">';
        
        # Name if existant
        $out .= '<b>'.$r['surname'] .($r['firstname'] <> ''? ', '.$r['firstname']:'').'</b>';
        if (strlen($r['surname'] .$r['firstname'])>0) $out .= '<br>';

        # Function/department if existant
        if ($r['surname'].$r['firstname'] == '') $out .= '<b>';
                
        if ($r['department'] != '') $out .= $this->names(array($r['department']));
        
        if (strlen($r['department'])>0) $out .= '<br>';
        if ($r['surname'].$r['firstname'] == '') $out .= '</b>';
            
        # Telephone
        if ($r['tel1'] <> '') $out .= '<br>Tel.: '.$this->names(array($r['tel1']));
        
        # TS: Adresse
        if ($r['tel2'].$r['cfunction']<>'') $out .= '<br>'.$this->names(array($r['tel2'],$r['cfunction']));
        # Fax
        if ($r['fax']<>'') $out .= '<br>Fax: '.$r['fax'];
        
        # Mail
        if ($r['email']<>'') $out .= '<br>Mail: <a href="mailto:'.$r['email'].'">'.$r['email'].'</a>';
        
        $out .= '</div>';
        
        if ($r['photo'] != false) $out .= "<img class='photo' src='data:image/jpg;base64,".($r['photo'])."'>";
        
        $out .= '</div>';
        
        if ($r['description']<>'') $out .= $r['description'];
        
        if ($this->loggedin) {
            $out .= '<div class="footer">';
        
            $out .= 'Nr. '.$r['id'];
        
            if ($this->editor && $target != false) {
                $out .= '<span class="buttons">';
                $out .= '<a href="'.wl($target,'editcontact='.$r['id']).'">'.$this->getLang('exec edit').'</a>';
                $out .= '<a href="'.wl($target,'erasecontact='.$r['id']).'" onclick="return confirm(\'Sure?\');">'.$this->getLang('exec delete').'</a>';
                $out .= '</span>';
            }
        
            $out .= '</div>';
        }
        
        $out .= '</div>';
        
        return $out;
    }
    
    
    /* Get single contact data per id
     * 
     * @param id: id in the database (unique)
     * 
     * @return:
     * - false if id is not found
     * - array containing all data of the contact
     */
    function getContactData($cid){
        try {
            $db_helper = plugin_load('helper', 'addressbook_db');
            $sqlite = $db_helper->getDB();
        } catch (Exception $e) {
            msg($e->getMessage(), -1);
            return false;
        }
        
        $sql = "SELECT * FROM addresslist WHERE id = $cid";
        $query = $sqlite->query($sql);
        
        if (ADRESSBOOK_SQL) msg("<code>$sql</code>",2);
        
        if ($sqlite->res2count($query) ==0){
            msg ("Contact not found (id: $cid)",-1);
            return false;
        }

        $res = $sqlite->res2arr($query)[0];
        
        return $res;
    }
    
    
    /* 
     * @return: Array - Contact data in the sequence they should be displayed
     * ! result does not contain the photo and the id
     */
    function getKeys(){
        return Array('firstname','surname','department','cfunction','tel1','tel2','fax','email','description');
    }
    
    
    /* Loads the data from the $_REQUEST-Variables into an array
     * the image is loaded as a blob
     * 
     * @return array
     */
    function loadFormData(){
        $res = Array();
        
        $keys = $this->getKeys();#Array('firstname','surname','cfunction','description');
        
        foreach ($keys as $k) $res[$k] = $_REQUEST[$k];
        
        # Validate and load photo data
        if (isset($_FILES) && $_FILES['photo']['error'] == UPLOAD_ERR_OK && $_FILES['photo']['tmp_name']!='') {
            if (filesize($_FILES['photo']['tmp_name']) > (2*1024*1024)) {
                msg('Uploaded photo exceeds 2 MB. Not processed',-1);
                $res['photo'] = false;
            } elseif (exif_imagetype($_FILES['photo']['tmp_name']) != IMAGETYPE_JPEG){
                msg('Image ist not *.jpg file. Not processed.',-1);
                $res['photo'] = false;
            } else {
                $pic = $this->scaleJPG($_FILES['photo']['tmp_name']);
                $res['photo'] = base64_encode($pic);
                unset($pic);
            }
        } else {
            $res['photo'] = false;
            if ($_FILES['photo']['error'] != UPLOAD_ERR_OK && $_FILES['photo']['tmp_name']!='' ) msg('Image could not be uploaded. Error code: '.$_FILES['photo']['error'],-1);
        }
               
        return $res;
    }
    
    /* SaveData-Function: Saves Data to an existing contact or adds a new contact
     * 
     * @param $info: Array containing the form data
     * 
     * additions params used:
     * $_REQUEST['blob']      - contains the blob of an existing id
     * $_REQUEST['contactid'] - the id of the existing contact or "new" for a new one
     */
    function saveData($info){
        #msg(print_r($info,true));
        
        if ($info['surname'] =='' && $info['cfunction']=='') {
            msg('Please enter either a last name or a function.',-1);
            return false;
        } else {
            try {
                $db_helper = plugin_load('helper', 'addressbook_db');
                $sqlite = $db_helper->getDB();
                
            } catch (Exception $e) {
                msg($e->getMessage(), -1);
                return false;
            }
            
            $keys = array_keys($info);
            
            # Use existing photo in existing id
            if ($_REQUEST['contactid'] != 'new' && isset($_REQUEST['blob']) && $_REQUEST['removephoto'] != 'Remove photo' && $info['photo']== false) {
                $info['photo'] = $_REQUEST['blob'];
                # msg("Keep existing photo",2);
            }
            
            if ($info['photo']!== false) $blob = $info['photo'];
            
            # Add new contact
            if ($_REQUEST['contactid'] == 'new') {
                #if ($info['photo']!== false) $blob = $info['photo'];
                $sql = "INSERT INTO addresslist
                        (".implode(',',$keys).") VALUES 
                        (";
                
                foreach ($keys as $k) {
                    if ($k != 'photo') $sql .= "'".$info[$k]."',";
                    if ($k == 'photo') $sql .= "'$blob'";
                }
                
                $sql .= ')';

                unset ($blob);
                
                $res = $sqlite->query($sql);
                
                if (ADRESSBOOK_SQL) msg("<code>$sql</code>",2);
                
                msg("Added new contact",1);
                return true;
            } else {
                
            # Update existing contact
            
                $sql = "UPDATE addresslist SET ";
                
                foreach ($keys as $k) {
                    if ($k != 'photo') $sql .= " $k = '".$info[$k]."', ";
                    if ($k == 'photo')  $sql .= " $k = '$blob'";
                }
                
                $sql .= " WHERE id = ".$_REQUEST['contactid'];
                
                if (ADRESSBOOK_SQL) msg("<code>$sql</code>",2);
                
                $res = $sqlite->query($sql);
                msg("Contact data updated",1);
                return true;
            }
        }
    }
    

    function deleteContact($cid){
        try {
            $db_helper = plugin_load('helper', 'addressbook_db');
            $sqlite = $db_helper->getDB();
        } catch (Exception $e) {
            msg($e->getMessage(), -1);
            return false;
        }
        
        $sql = "DELETE FROM addresslist WHERE id = $cid";
        $sqlite->query($sql);
        
        if (ADRESSBOOK_SQL) msg("<code>$sql</code>",2);
        
        msg('Contact deleted',1);
        
    }
    
    /* Scale image to a maximum size (either width or height maximum)
     * from jpg to jpg
     * 
     * @param $filename: name of source file
     * @param $max: maximum width or heigth (depending on which is longer)
     * @param $quality: compression rate
     * 
     * return: blob with jpg
     */
    function scaleJPG($filename,$max=120,$quality=70){

        # calculate new dimensions
        list($width, $height) = getimagesize($filename);

        if ($width > $height) {$percent = $max/$width;} else {$percent = $max/$height;}

        $newwidth = $width * $percent;
        $newheight = $height * $percent;

        # load image
        $thumb = imagecreatetruecolor($newwidth, $newheight);
        $source = imagecreatefromjpeg($filename);

        # scale
        imagecopyresized($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

        # output
        ob_start();
        imagejpeg($thumb,NULL,$quality);
        $result = ob_get_contents();
        ob_end_clean();
        
        return $result;
    }
    
    
    /* Searches the database for the occurence of search terms.
     * The search uses AND-operator for multiple terms
     * 
     * @param $text: search terms separated with a blank space
     * 
     * return:
     * - boolean false: no matches
     * - array with matches
     */
    function searchDB($text=false,$order='surname,firstname'){
        if ($text == false || strlen($text) < 2) {msg("Invalid search.");return;}
        
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
        $sql .= " ORDER BY $order";
        
        $query = $sqlite->query($sql);
        $res = $sqlite->res2arr($query);
        
        if (ADRESSBOOK_SQL) msg("<code>$sql</code>",2);
        
        if ($sqlite->res2count($sqlite->query($sql)) == 0) {
            msg("No matches found",2);
            return false;
        }
        
        return $res;
    }
    
    
    function getList($filters=false,$order="surname,firstname,cfunction,department"){
        try {
            $db_helper = plugin_load('helper', 'addressbook_db');
            $sqlite = $db_helper->getDB();
        } catch (Exception $e) {
            msg($e->getMessage(), -1);
            return false;
        }
        
        # Get all elements if no filters are set
        if ($filters === false){
            $sql = "select * from addresslist ORDER BY $order";
        }
        
        # Get one department
        if (isset($filters['department'])){
            $sql = "select * from addresslist WHERE UPPER(department) = UPPER('".$filters['department']."') ORDER BY $order";
        }
        
        $query = $sqlite->query($sql);
        $res = $sqlite->res2arr($query);
        
        if (ADRESSBOOK_SQL) msg("<code>$sql</code>",2);
        
        # return false if no matches are found
        if ($sqlite->res2count($sqlite->query($sql)) == 0) {
            msg("No matches found for list",2);
            return false;
        }
        
        # Adjust content for sorting purposes when information is missing / not stated
        foreach ($res as &$r) {
            if ($r['surname'] == '' && $r['department'] == '') $r['department'] = $this->getLang('departments');
            if ($r['surname'] != '' && $r['department'] == '') $r['department'] = $this->getLang('general');
        }
        
        return $res;
    }
    
    
    /* Creates a list of values from a column. Each element is listed
     * once.
     * 
     * @param string $column: choose column from which to get data
     * @param boolean $htmlselect: creates output string containing html for a dropdown box
     * 
     * @return:
     * - array: elements founds
     * - string: with html for checkbox
     */
    function getCriteria($column,$htmlselect = true){
        $allowed = array('cfunction','department');
        if (!in_array($column,$allowed)) return false;
        
        try {
            $db_helper = plugin_load('helper', 'addressbook_db');
            $sqlite = $db_helper->getDB();
        } catch (Exception $e) {
            msg($e->getMessage(), -1);
            return false;
        }
        
        $sql = "SELECT DISTINCT $column FROM addresslist WHERE $column NOT LIKE '' ORDER by $column";
    
        $query = $sqlite->query($sql);
        $res = $sqlite->res2arr($query);
        
        if (ADRESSBOOK_SQL) msg("<code>$sql</code>",2);
        
        # return false if no matches are found
        if ($sqlite->res2count($sqlite->query($sql)) == 0) {
            #msg("No matches found for criteria",2);
            return false;
        }
        
        foreach ($res as $r) $n[] =$r[$column];
        if ($htmlselect){
            $res = '';
            $res .= ucfirst($column);
            $res .= '<select name="column" id="column">';
            $res .= "<option value='all'>All</option>";
            foreach ($n as $s) $res .= "<option value='$s'>$s</option>";
            $res .= '</select>';
            return $res;
        }
        
        return $n;
    }
    
    /* Helper function, similar to implode
     * Adds string elements to an array only if it is not empty and then implodes them
     */
    function names($list,$symbol=', '){
        $res = Array();
        foreach ($list as $l) if ($l != '')  $res[]=$l;
        return implode($symbol,$res);
    }
    
    
    /* Helper function
     * Return array with parameter=>value pairs
     * 
     * @param string $params = parameters in form "<somet_text>?param1=value1&param2=value2...paramN=valueN"

     * 
     * @return array(parameter1 => value1, ...)
     */
    function getValue($params){
        $params = substr($params,strpos($params,'?')+1);
        $opts = explode('&',$params);
        
        foreach ($opts as $o) {
            if (strpos($o,'=') == 0) {$res[$o] = false;} else {
                $t = explode('=',$o);
                $res[$t[0]] = $t[1];
            }
        }
        
        return $res;
    }
    
    
    /* Build an index showing contacts
     *
     *  @param $list: list of contacts
     *  @param $separator = db_field for which headers are created in the list.
     */
    function buildIndex($list=false,$separator=false,$target=false){
        
        # if no list ist stated, get all. If no entry in DB, return
        if ($list===false){
            $list =$this->getList();
            if ($list === false) return;
        }
        
        # Sort by Surname or Function->if no surname ist stated
        if (!$separator) usort($list,'contact_custom_double_sort');
        
        $out .= $this->getLang('elements').': '.count($list);
        $out .= '<div class="addressbook_list" style="column-width:20em;margin-top:3px;">';
        
        
        $sep = '';
        foreach ($list as $r){
            
            if ($separator !== false){
                if ($sep != $r[$separator]) {
                    $sep = $r[$separator];
                    $out .= '<h3>'.$r[$separator].'</h3>';
                }
            }
            
            $out .= '<span>';
            
            if ($r['surname'].$r['firstname'] <> '') {$names = true;} else {$names = false;}
            
            if ($target != false) $out .= '<a href="'.wl($target,'showcontact='.$r['id']).'">';
            
            $out .= $r['surname'] .($r['firstname'] <> ''? ', '.$r['firstname']:'');
            if (!$names) $out .= $this->names(array($r['cfunction'],$r['department']));
            
            if ($target != false) $out .= '</a>';
            
            if ($names && $r['department'] <>'') $out .= ' ('.$this->names(array($r['department'])).')';
            
            if ($r['tel1'].$r['tel2'] <> '') $out .= ' Tel: '.$this->names(array($r['tel1']));
			
			if ($r['tel2'].$r['cfunction'] <> '') $out .= ', '.$this->names(array($r['tel2'],$r['cfunction']));
            
            $out .= '</span>';
        }
        $out .= '</div>';
        
        return $out;

    }


    /* Builds a printable contact list
     * 
     * @param array $list = list of contact entries in format array[1..n](db_field => value)
     * 
     * @param string $separator = name of database field. This name is added as a
     *                            header between the contact entries. Important: The
     *                            entries should be sorted in first place according
     *                            to this speparator, otherweise there will be as many
     *                            headers as contacts!.
     *                            Allowed separators: 'department'
     * 
     * @param integer $entriesperpage = amount of list items per page, must be even!
     */
    function buildPrintList($list=false,$separator = false,$entriesperpage = 80){
        
		$pages = 0;
		$amount = 0;
		$this->preparePrintList($list,$separator,$entriesperpage,$pages,$amount);
		
        for ($p=0;$p<$pages;$p++) {
            
            $out .= '<table class="plugin_addressbook_print">';
        
			$out .= '<tr><th>Praxis</th><th>Adresse</th><th>Telefon</th></tr>';//<th>Fax</th></tr>';
            for ($row=0;$row<$entriesperpage/2;$row++) {
                
                unset($i);
                $i[] = ($p * $entriesperpage) + $row;
                $i[] = ($p * $entriesperpage) + $row + ($entriesperpage/2);
                $col = 0;
                
                #if ($i[0] < $amount) 
                $out .= '<tr'.($row % 2 == 1? ' style="background:lightgray"':'').'>';
                
                foreach ($i as $d) {
                    # Output title
                    if ($separator != false && $list[$d]['title'] == true) {
                        $out .= '<td style="font-weight:bold;text-decoration:underline;font-size:12px;text-align:left;background:white" colspan=4>'.$list[$d]['cfunction'].'</td>';
                        $col++;
                        if ($col < count($i)) $out .= '<td style="background:white;width:10px;"></td>';
                        } 
                    
                    # Output contact data
                    if ($d < $amount && !isset($list[$d]['title'])) {

                        $out .= '<td style="text-align:left">'.$this->names(array($list[$d]['surname'],$list[$d]['firstname'])).'</td>';
                        $out .= '<td style="text-align:left">'.$list[$d]['tel2'].', '.$list[$d]['cfunction'].'</td>';
                        $out .= '<td>'.$list[$d]['tel1'].'</td>';
                        //$out .= '<td>'.$list[$d]['fax'].'</td>';

                        $col++;
                        if ($col < count($i)) $out .= '<td style="background:white;width:10px;"></td>';
                    }
                    
                    # Fill with empty cells if there are no entries, so that the table is continued
                    /*if ($d> $amount) {
                        $out.= '<td colspan=4 style="background:white;">'.str_repeat('&nbsp;',15).'</td>';
                        $col++;
                        if ($col < count($i)) $out .= '<td style="background:white;width:10px;"></td>';
                    }*/
                    
                }

                $out .= '</tr>';
            
            }
            
            $out .= '</table>';
        
        }

        return $out;

    }


    /* Helper function that prepares variables for a printable contact list.
	 * Uses call by reference for all variables so that they can be used in the calling method.
     */
	
	function preparePrintList(&$list,&$separator,&$entriesperpage,&$pages,&$amount){
		# validation: separator type correct
        $allowed_separators = Array('department');
        if (!in_array($separator,$allowed_separators)) $separator = false;
        
        # validation entries per page must be even
        if ($entriesperpage % 2 == 1) $entriesperpage++;
        
        # if no list is stated, get all. If no entry in DB, return
        if ($list===false){
            $list =$this->getList(false,($separator == false? '': "$separator,").'surname,firstname,cfunction');
            if ($list === false) return;
        }
        
        # Sort by Surname or Function->if no surname ist stated
        if (!$separator) usort($list,'contact_custom_double_sort');
        
        $amount = count($list);
        $pages = ceil($amount/$entriesperpage);
        
        # $separator = 'department';
        # process list before generating the table
        if ($separator == 'department'){
            $dep = $list[$c+1]['department'];
            $insert = Array();
            $i_count = 0;
            for ($c=0;$c<$amount;$c++) {
                if ($list[$c+1]['department'] != $dep) {

                    
                    $i_count++;
                    
                    if ($c+$i_count % $entriesperpage > 0) {
                        
                        $insert[] = array('position' => $c+$i_count, 'title' =>true, 'cfunction' => '');
                        $i_count++;
                    }
                    
                    $insert[] = array('position' => $c+$i_count, 'title' =>true, 'cfunction' => $list[$c+1]['department']);
                    
                    
                    $dep = $list[$c+1]['department'];
                    
                }

            }
            
            foreach ($insert as $i){
                
                unset($temp);
                for ($c=0;$c<count($list);$c++){

                    if ($c==$i['position']){                        
                        $temp[] = array('cfunction'=>$i['cfunction'],'title' => true);
                    }
                    
                    $temp[] = $list[$c];
                
                }
                $list = $temp;
                
            }
            
            $amount = count($list);
            $pages = ceil($amount/$entriesperpage);
        }
	}
 
 
    /* Builds a printable contact list as ODT output
     * 
     * @param   $renderer Doku_Renderer the current renderer object
	 *
     * @param array $list = list of contact entries in format array[1..n](db_field => value)
     * 
     * @param string $separator = name of database field. This name is added as a
     *                            header between the contact entries. Important: The
     *                            entries should be sorted in first place according
     *                            to this speparator, otherweise there will be as many
     *                            headers as contacts!.
     *                            Allowed separators: 'department'
     * 
     * @param integer $entriesperpage = amount of list items per page, must be even!
     */
    function buildPrintList4ODT(&$renderer, $list=false,$separator = false,$entriesperpage = 80){
		
		$pages = 0;
		$amount = 0;
		$this->preparePrintList($list,$separator,$entriesperpage,$pages,$amount);
		
		$printfax = false;
		
		
        for ($p=0;$p<$pages;$p++) {
			$renderer->table_open(2,1);
			
			/* //code for additional columns
			$renderer->tablerow_open();
			$renderer->tableheader_open(4,1);
			$renderer->cdata('Praxis');
			$renderer->tableheader_close();
			$renderer->tableheader_open(1,1);
			$renderer->cdata('Telefon');
			$renderer->tableheader_close();
			$renderer->tableheader_open(1,1);
			$renderer->tableheader_close();
			$renderer->tableheader_open(1,1);
			$renderer->tableheader_close();
			$renderer->tableheader_open(1,1);
			$renderer->tableheader_close();
			/*$renderer->tableheader_open(1,1);
			$renderer->cdata('Fax');
			$renderer->tableheader_close();*/
			//$renderer->tablerow_close();
			
        
            for ($row=0;$row<$entriesperpage/2;$row++) {
				
				unset($i);
                $i[] = ($p * $entriesperpage) + $row;
                $i[] = ($p * $entriesperpage) + $row + ($entriesperpage/2);
                $col = 0;
			
				$closed = true;
				foreach ($i as $d) {
                    # Output title
                    if ($separator != false && $list[$d]['title'] == true) {
						
						$renderer->tablecell_open();
						$renderer->p_open();
						$renderer->cdata($list[$d]['cfunction']);
						$renderer->p_close();
						$renderer->tablecell_close();
						$col++;
                    } 
                    
                    # Output contact data
                    if ($d < $amount && !isset($list[$d]['title'])) {
						
						if ($d % 2 == 0) {
							$renderer->tablerow_open();
							$closed = false;
						}

						$renderer->tablecell_open(1,"center",1); // "center" ist hier wichtig, da die Zelle automatisch einen Paragraph öffnet, dessen Style hier nicht übergeben werden kann!
						$renderer->strong_open();
						$renderer->cdata($this->names(array($list[$d]['surname'],$list[$d]['firstname']),' '));
						$renderer->strong_close();
						$renderer->p_close();
						$renderer->p_open("Table_20_Contents");
						$renderer->cdata($list[$d]['tel2']);
						$renderer->p_close();
						$renderer->p_open("Table_20_Contents");
						$renderer->cdata($list[$d]['cfunction']);
						$renderer->p_close();
						$renderer->p_open("Table_20_Contents");
						$renderer->cdata($list[$d]['tel1']);
						$renderer->p_close();
						$renderer->tablecell_close();
						
						/* //code for additional columns
						$renderer->tablecell_open();
						$renderer->p_open();
						$renderer->cdata($list[$d]['tel1']);
						$renderer->p_close();
						$renderer->tablecell_close();*/
						
						/*$renderer->tablecell_open();
						$renderer->p_open();
						$renderer->cdata($list[$d]['fax']);
						$renderer->p_close();
						$renderer->tablecell_close();*/
						
						/*$renderer->tablecell_open();
						$renderer->tablecell_close();
						$renderer->tablecell_open();
						$renderer->tablecell_close();
						$renderer->tablecell_open();
						$renderer->tablecell_close();*/
						
						if ($d % 2 == 1) {
							$renderer->tablerow_close();
							$closed = true;
						}

                        $col++;
                    }
                }
			}
			
			if (!$closed) {
				$renderer->tablecell_open();
				$renderer->tablecell_close();
				$renderer->tablerow_close();
			}
			$renderer->table_close();
		}
    }
    
    /* Copies the first entry of the database multiple time for testing purposes
     * Beware: This can take minutes!
     * 
     * @param $n: amount of copie to be made
     */
    function fillDB($n=0){
        try {
            $db_helper = plugin_load('helper', 'addressbook_db');
            $sqlite = $db_helper->getDB();
        } catch (Exception $e) {
            msg($e->getMessage(), -1);
            return false;
        }
        
        $sql = "select * from addresslist  WHERE id = 1";
        
        $query = $sqlite->query($sql);
        $res = $sqlite->res2arr($query)[0];
        unset($res['id']);
        
        for ($c=0;$c<$n;$c++){
            $sql = "INSERT INTO addresslist
                    (firstname,surname,cfunction,tel1,tel2,fax,email,department,description,photo) VALUES 
                    (";
            foreach ($res as $k=>$r) $sql.= "'$r'".($k=='photo'? ')':',');
            $query = $sqlite->query($sql);
        }
    }

}


/* Inspired by https://www.php.net/manual/de/function.asort.php 
 * 
 * Callback function to sort the contact list by surname OR
 * cfunction if nor surname ist stated
 * 
 * */
function contact_custom_double_sort($a,$b) {
    if ($a['surname'] == '') $a['surname'] = $a['cfunction'];
    if ($b['surname'] == '') $b['surname'] = $b['cfunction'];
    return $a['surname'] > $b['surname'];
}


function addressbook_debug_show($direct=true){
    $out = '<pre>'.print_r($_REQUEST,true).'</pre>';
    if ($direct) echo $out;
    return $out;
}
