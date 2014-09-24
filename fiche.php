<?php

require('config.php');
dol_include_once('/timesheet/class/timesheet.class.php');
dol_include_once('/projet/class/project.class.php');
dol_include_once('/projet/class/task.class.php');
dol_include_once('/societe/class/societe.class.php');
dol_include_once('/timesheet/lib/timesheet.lib.php');
dol_include_once('/core/class/html.formprojet.class.php');

if(!$user->rights->timesheet->user->read) accessforbidden();

// Load traductions files requiredby by page
$langs->Load("timesheet@timesheet");

// Get parameters
_action();

// Protection if external user
if ($user->societe_id > 0)
{
	accessforbidden();
}

function _action() {
	global $user;

	$PDOdb=new TPDOdb;
	$timesheet = new TTimesheet;
	
	/*******************************************************************
	* ACTIONS
	*
	* Put here all code to do according to value of "action" parameter
	********************************************************************/

	if(isset($_REQUEST['action'])) {
		switch($_REQUEST['action']) {
			case 'new':
			case 'add':
				$timesheet->set_values($_REQUEST);
				_fiche($timesheet,'new');
				break;

			case 'edit'	:
				$timesheet->load($PDOdb, $_REQUEST['id']);
				_fiche($timesheet,'edit');
				break;

			case 'save':
				if(!empty($_REQUEST['id'])) $timesheet->load($PDOdb, $_REQUEST['id']);
				$timesheet->set_values($_REQUEST);
				$timesheet->save($PDOdb);
				?>
				<script language="javascript">
					document.location.href="<?php echo dirname($_SERVER['PHP_SELF']); ?>/fiche.php?id=<?php echo $timesheet->rowid; ?>";					
				</script>
				<?
				break;

			case 'delete':
				$timesheet->load($PDOdb, $_REQUEST['id']);
				$timesheet->delete($PDOdb);
				
				?>
				<script language="javascript">
					document.location.href="<?php echo dirname($_SERVER['PHP_SELF']); ?>/liste.php?delete_ok=1";					
				</script>
				<?
				
				break;
		}
		
	}
	elseif(isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
		$timesheet->load($PDOdb, $_REQUEST['id']);
		_fiche($timesheet, 'view');
	}
	else{
		?>
		<script language="javascript">
			document.location.href="<?php echo dirname($_SERVER['PHP_SELF']); ?>/liste.php";					
		</script>
		<?
	}


	
	
}

function _fiche(&$timesheet, $mode='edit') {
	
	global $langs,$db,$conf,$user;

	llxHeader('',$langs->trans('FicheTimesheet'),'','');
	print dol_get_fiche_head(timesheetPrepareHead( $timesheet, 'timesheet') , 'fiche', $langs->trans('FicheTimesheet'));

	$form=new TFormCore($_SERVER['PHP_SELF'],'form','POST');
	$form->Set_typeaff($mode);
	
	echo $form->hidden('id', $timesheet->rowid);
	
	if ($mode=='new' || $mode=='edit'){
		echo $form->hidden('action', 'save');
	}
	else {
		echo $form->hidden('action', 'edit');
	}
	
	echo $form->hidden('entity', $conf->entity);

	$TBS=new TTemplateTBS();
	$liste=new TListviewTBS('timesheet');

	$TBS->TBS->protect=false;
	$TBS->TBS->noerr=true;


	/*
	 * Affichage informations générales
	 */
	print $TBS->render('tpl/fiche.tpl.php'
		,array()
		,array(
			'timesheet'=>array(
				'id'=>$timesheet->rowid
				,'project'=>_fiche_visu_project($timesheet,$mode)
				,'societe'=>_fiche_visu_societe($timesheet,$mode)
				,'date_deb'=>$form->calendrier('', 'date_deb', $timesheet->date_deb)
				,'date_fin'=>$form->calendrier('', 'date_fin', $timesheet->date_fin)
			)
			,'fiche'=>array(
				'mode'=>$mode
			)
		)
	);

	//Construction du nombre de colonne correspondant aux jours
	$TJours = array();
	$TFormJours = array();

	$date_deb = new DateTime($timesheet->get_date('date_deb','Y-m-d'));
	$date_fin = new DateTime($timesheet->get_date('date_fin','Y-m-d'));
	$diff = $date_deb->diff($date_fin);
	$diff = $diff->format('%d') +1;

	$date_deb->sub(new DateInterval('P1D'));

	for($i=1;$i<=$diff;$i++){
		$date_temp = $date_deb->add(new DateInterval('P1D'));
		$TJours[$date_deb->format('d/m')] = $date_deb->format('D');
		
		//Chargement du formulaire se saisie des temps
		$TFormJours[$date_deb->format('d/m')] = $form->timepicker('', 'temps'.$i, '',5);
	}
	
	$doliform = new Form($db);
	
	//Charger les lignes existante dans le timeSheet
	$TligneTimesheet=array();

	$TBS=new TTemplateTBS();
	
	/*
	 * Affichage tableau de saisie des temps
	 */
	print $TBS->render('tpl/fiche_saisie.tpl.php'
		,array(
			'ligneTimesheet'=>$TligneTimesheet,
			'jours'=>$TJours,
			'formjour'=>$TFormJours
		)
		,array(
			'timesheet'=>array(
				'rowid'=>0
				,'id'=>$timesheet->rowid
				,'services'=>$doliform->select_produits_list('','serviceid[]','1')
				,'consultants'=>$doliform->select_dolusers('','userid[]')
			)
			,'view'=>array(
				'mode'=>$mode
				,'nbChamps'=>count($asset->TField)
				,'head'=>dol_get_fiche_head(timesheetPrepareHead($asset)  , 'field', $langs->trans('AssetType'))
				,'onglet'=>dol_get_fiche_head(array()  , '', $langs->trans('AssetType'))
			)
			
		)	
		
	);
	 
	echo $form->end_form();

	llxFooter('$Date: 2011/07/31 22:21:57 $ - $Revision: 1.19 $');
}

function _fiche_visu_project(&$timesheet, $mode){
	global $db;

	if($mode=='edit' || $mode=='new') {
		ob_start();

		$html=new FormProjets($db);
		$html->select_projects($timesheet->fk_societe, $timesheet->fk_project, 'fk_project');

		return ob_get_clean();

	}
	else {
		if($timesheet->fk_project > 0) {
			require_once(DOL_DOCUMENT_ROOT.'/projet/class/project.class.php');

			$project = new Project($db);
			$project->fetch($timesheet->fk_project);

			return '<a href="'.DOL_URL_ROOT.'/projet/fiche.php?id='.$timesheet->fk_project.'" style="font-weight:bold;">'.img_picto('','object_project.png', '', 0).' '.$project->ref.'</a>';
		} else {
			return 'Non défini';
		}
	}
}

function _fiche_visu_societe(&$timesheet, $mode) {
	global $db;

	if($mode=='edit' || $mode=='new') {
		ob_start();

		$html=new Form($db);
		echo $html->select_company($timesheet->fk_societe,'fk_societe','',1);

		return ob_get_clean();

	}
	else {
		if($timesheet->fk_societe > 0) {
			require_once(DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php');

			$soc = new Societe($db);
			$soc->fetch($timesheet->fk_societe);

			return '<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid='.$timesheet->fk_societe.'" style="font-weight:bold;">'.img_picto('','object_company.png', '', 0).' '.$soc->nom.'</a>';
		} else {
			return 'Non défini';
		}
	}
}

?>