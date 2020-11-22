<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('google_agenda');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<div class="row row-overflow">
	<div class="col-lg-2 col-md-3 col-sm-4">
		<div class="bs-sidebar">
			<ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
			<li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="{{Rechercher}}" style="width: 100%"/></li>
				
				<ul id="ul_eqLogic" class="nav nav-list bs-sidenav">Mes Agendas
				<a class="btn btn-default eqLogicAction" style="width : 100%;margin-top : 5px;margin-bottom: 5px;" data-action="ajout_agenda"><i class="fa fa-plus-circle"></i> {{Ajouter un agenda}}</a>
					<?php
					foreach ($eqLogics as $eqLogic) {
						if ($eqLogic->getConfiguration('type_equipement') == 'agenda') {	
							$opacity = ($eqLogic->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
							echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '" style="' . $opacity .'"><a>' . $eqLogic->getHumanName(true) . '</a></li>';
						}
					}
					?>
				</ul>
				<ul id="ul_eqLogic" class="nav nav-list bs-sidenav filtres" style="display:none">Mes filtres
				<a class="btn btn-default eqLogicAction" style="width : 100%;margin-top : 5px;margin-bottom: 5px;" data-action="ajout_filtre"><i class="fa fa-plus-circle"></i> {{Ajouter un filtre}}</a>
					<?php
					
					foreach ($eqLogics as $eqLogic) {
						if ($eqLogic->getConfiguration('type_equipement') == 'filtre') {	
							$opacity = ($eqLogic->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
							echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '" style="' . $opacity .'"><a>' . $eqLogic->getHumanName(true) . '</a></li>';
						}
					}
					?>
				</ul>
			</ul>
		</div>
	</div>

	<div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">
		<legend><i class="fa fa-cog"></i>  {{Gestion}}</legend>
		<div class="eqLogicThumbnailContainer">
			
			<div class="cursor eqLogicAction" data-action="ajout_agenda" style="text-align: center; background-color : #ffffff; height : 120px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >
				<i class="fa fa-plus-circle" style="font-size : 6em;color:#94ca02;"></i>
				<br>
				<span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-word;white-space: pre-wrap;word-wrap: break-word;color:#94ca02">{{Ajouter un agenda}}</span>
			</div>
			<div class="cursor eqLogicAction" id="btn_filtre" data-action="ajout_filtre" style="display:none;text-align: center; background-color : #ffffff; height : 120px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >
				<i class="fa fa-plus-circle" style="font-size : 6em;color:#94ca02;"></i>
				<br>
				<span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-word;white-space: pre-wrap;word-wrap: break-word;color:#94ca02">{{Ajouter un filtre}}</span>
			</div>
			<div class="cursor eqLogicAction" data-action="gotoPluginConf" style="text-align: center; background-color : #ffffff; height : 120px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;">
				<i class="fa fa-wrench" style="font-size : 6em;color:#767676;"></i>
				<br>
				<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-word;white-space: pre-wrap;word-wrap: break-word;color:#767676">{{Configuration}}</span>
			</div>
		</div>
		<legend><i class="fa fa-table"></i> {{Mes Agendas}}</legend>
		<!--<input class="form-control" placeholder="{{Rechercher}}" style="margin-bottom:4px;" id="in_searchEqlogic" />-->
		<div class="eqLogicThumbnailContainer" id="equipements_agendas">
			<?php
				foreach ($eqLogics as $eqLogic) {
					if ($eqLogic->getConfiguration('type_equipement') == 'agenda') {	
						$opacity = ($eqLogic->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
						echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="text-align: center; background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;' . $opacity . '" >';
						echo '<img src="' . $plugin->getPathImgIcon() . '" height="105" width="95" />';
						echo "<br>";
						echo '<span class="name" style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;">' . $eqLogic->getHumanName(true, true) . '</span>';
						echo '</div>';
					}
				}
			?>
		</div>
		<script>
			if ($('div #equipements_agendas .eqLogicDisplayCard').length != 0){
				$('#btn_filtre').show();
				$('div .eqLogicThumbnailContainer .eqLogicAction').last().css('left', 280);  
			}
		</script>
		<legend style="display:none" id="legende_filtres"><i class="fa fa-table" ></i> {{Mes Filtres}}</legend>
		<div class="eqLogicThumbnailContainer" style="display:none" id="equipements_filtres">
			<?php
			foreach ($eqLogics as $eqLogic) {
				if ($eqLogic->getConfiguration('type_equipement') == 'filtre') {
					$opacity = ($eqLogic->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
					echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="text-align: center; background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;' . $opacity . '" >';
					echo '<img src="' . $plugin->getPathImgIcon() . '" height="105" width="95" />';
					echo "<br>";
					echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;">' . $eqLogic->getHumanName(true, true) . '</span>';
					echo '</div>';
				}
			}
			?>
		</div>
	</div>

	<div class="col-lg-10 col-md-9 col-sm-8 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
		<a class="btn btn-success eqLogicAction pull-right" data-action="save"><i class="fa fa-check-circle"></i> {{Sauvegarder}}</a>
		<a class="btn btn-danger eqLogicAction pull-right" data-action="remove"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
		<a class="btn btn-default eqLogicAction pull-right" data-action="configure"><i class="fa fa-cogs"></i> {{Configuration avancée}}</a>
		<ul class="nav nav-tabs" role="tablist">
			<li role="presentation"><a class="eqLogicAction cursor" aria-controls="home" role="tab" data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a></li>
			<li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fa fa-tachometer"></i> {{Equipement}}</a></li>
			<li id="filtre" role="presentation"><a href="#actiontab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i> {{Actions}}</a></li>
			<li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i> {{Commandes}}</a></li>
		</ul>
		<div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
			<div role="tabpanel" class="tab-pane active" id="eqlogictab">
				<br/>
				<form class="form-horizontal">
					<fieldset>
						<div class="form-group">
							<label class="col-sm-3 control-label">{{Nom de l'équipement agenda}}</label>
							<div class="col-sm-3">
								<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
								<input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement agenda}}"/>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label" >{{Objet parent}}</label>
							<div class="col-sm-3">
								<select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
									<option value="">{{Aucun}}</option>
									<?php
										 $options = '';
										 foreach ((jeeObject::buildTree(null, false)) as $object) {
										 $options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) .$object->getConfiguration('icon'). $object->getName() . '</option>';
										 }
										 echo $options;
									?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label class="col-sm-3 control-label"></label>
							<div class="col-sm-9">
								<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable">{{Activer}}</label>
								<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible">{{Visible}}</label>
							</div>
						</div>
						<div class="form-group" style="display: none;">
							<label class="col-sm-3 control-label">{{Type équipement}}</label>
							<div class="col-sm-9">
							<input  type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="type_equipement" />
								
							</div>
						</div>
						
						<div id="div_type_agenda">
						<div class="form-group">
							<label class="col-sm-3 control-label">{{URL de retour}}</label>
							<div class="col-sm-9">
								<span><?php echo network::getNetworkAccess('external') . '/plugins/google_agenda/core/php/callback.php?apikey=' . jeedom::getApiKey('google_agenda') . '&eqLogic_id='; ?><span class="span_googleCallbackId"></span></span>
							</div>
						</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Client ID}}</label>
								<div class="col-sm-6">
									<input id="client_id" type="text" class="eqLogicAttr form-control" data-l1key="configuration1" data-l2key="client_id"/>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Secret key}}</label>
								<div class="col-sm-6">
									<input id="client_secret"type="text" class="eqLogicAttr form-control" data-l1key="configuration1" data-l2key="client_secret"/>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Lier}}</label>
								<div class="col-sm-2">
									<a class="btn btn-default" id="bt_linkToUser"><i class='fa fa-refresh'></i> {{Lier à un utilisateur}}</a>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Mettre à jour les agendas}}</label>
								<div class="col-sm-2">
									<a class="btn btn-default" id="bt_MAJ_agendas"><i class='fa fa-refresh'></i> {{MAJ}}</a>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Calendrier à surveiller}}</label>
								<div class="col-sm-9" id="div_listCalendar">
								</div>
							</div>
						
						
						</div>
						<div id="div_Type_filtre">
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Filtre d'évènement}}</label>
								<div class="col-sm-6">
									<input id="filtre"type="text" class="eqLogicAttr form-control" data-l1key="configuration1" data-l2key="filtre"/>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Rechercher dans}}</label>
								<div class="col-sm-6">
									<label class="checkbox-inline"><input id="sur_titre" type="checkbox" class="eqLogicAttr" data-l1key="configuration1" data-l2key="sur_titre"/>{{Titre}}</label>
									<label class="checkbox-inline"><input id="sur_contenu" type="checkbox" class="eqLogicAttr" data-l1key="configuration1" data-l2key="sur_contenu"/>{{Contenu}}</label>
							
								</div>
							</div>
							
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Equipement dans lequel rechercher}}</label>
								<div class="col-sm-6">
								<select id="equipement" class="eqLogicAttr form-control" data-l1key="equipement">
								<option value="">{{Aucun}}</option>
									<?php 
									
										foreach ($eqLogics as $eqLogic) {
											if($eqLogic-> getConfiguration("type_equipement","") == "agenda"){
												echo '<option value="' . $eqLogic->getId() . '">' . $eqLogic->getName() . '</option>';
											}
										}
									?>
								</select>
								</div>
							</div>
						</div>
					</fieldset>
				</form>
			</div> <!--fin tab panel eqlogictab-->
			<div role="tabpanel" class="tab-pane" id="actiontab">

				<div id="div_calendar"></div>
				<form class="form-horizontal" id="eventtab">
					<fieldset>
						<div>
							<legend> {{Action(s) à executer au début :}} <a class="btn btn-success btn-xs" id="Ajout_action_debut" style="margin-left: 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter Action}}</a> </legend>
							<div id="div_action_debut"></div>
						</div>

						<br/>

						<div>
							<legend> {{Action(s) à executer à la fin :}} <a class="btn btn-success btn-xs" id="Ajout_action_fin" style="margin-left: 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter Action}}</a> </legend>
							<div id="div_action_fin"></div>
						</div>

						<br/>       
					</fieldset>
				</form>              

			

			</div> <!--fin tab panel eventtab-->
			<div role="tabpanel" class="tab-pane" id="commandtab">
				<a class="btn btn-success btn-sm cmdAction pull-right" data-action="add" style="margin-top:5px;"><i class="fa fa-plus-circle"></i> {{Commandes}}</a><br/><br/>
				<table id="table_cmd" class="table table-bordered table-condensed">
					<thead>
					<tr>
						<th>{{ID}}</th>
						<th>{{Nom}}</th>
						<th>{{Type}}</th>
						<th>{{Action}}</th>
					</tr>
					</thead>
					<tbody>
					</tbody>
				</table>
			</div>
		</div>

	</div>
</div>

<?php include_file('desktop', 'google_agenda', 'js', 'google_agenda');?>
<?php include_file('core', 'plugin.template', 'js');?>