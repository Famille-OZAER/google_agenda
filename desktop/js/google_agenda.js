
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


 $("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$('document').ready(function(){
	//console.log($('div #equipements_agendas .eqLogicDisplayCard'))
	return;
	if ($('div #equipements_agendas .eqLogicDisplayCard').length != 0){
		$('#btn_filtre').show();
		$('#legende_filtres').show();
		$('#equipements_filtres').show();
		var p = $('div .eqLogicAction').first();
$('div .eqLogicThumbnailContainer .eqLogicAction').last().css('left', 280);  
	}
});
$('#bt_linkToUser').on('click', function () {
    $.ajax({
        type: "POST", 
        url: "plugins/Google_agenda/core/ajax/Google_agenda.ajax.php", 
        data: {
            action: "linkToUser",
            id: $('.eqLogic .eqLogicAttr[data-l1key=id]').value()
        },
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            window.location.href = data.result.redirect;
        }
    });
});

function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="id" style="display:block;"></span>';
	tr += '</td>';
	tr += '<td>';   
	tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" style="width : 140px;" placeholder="{{Nom}}">';
    tr += '</td>';
    tr += '<td>';
    tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
    tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
    tr += '</td>';
    tr += '<td>';
    if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
    }
    tr += '</td>';
    tr += '</tr>';
    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
    if (isset(_cmd.type)) {
        $('#table_cmd tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }
    jeedom.cmd.changeType($('#table_cmd tbody tr:last'), init(_cmd.subType));
}
function printEqLogic(_eqLogic) {
    //$('.nav-tabs a[href="#eqlogictab"]').tab('show');
	actionOptions = [];
	if (!isset(_eqLogic)) {var _eqLogic = {configuration: {}};}
	if (!isset(_eqLogic.configuration)) {_eqLogic.configuration = {};}
	$('.span_googleCallbackId').empty().append(_eqLogic.id);
	if (_eqLogic.configuration.type_equipement == 'agenda') {
		$('#filtre').hide();
		$('#div_Type_filtre').hide();
		$('#div_type_agenda').show();
		$('#div_listCalendar').empty();
		$('#div_listCalendar_filtre').empty();
		$('input[id=client_id]').val(_eqLogic.configuration.client_id);
		$('input[id=client_secret]').val( _eqLogic.configuration.client_secret);
		html = '<div class="list_calendars">';
		for (var i = 0; i < _eqLogic.configuration.agendas.length; i++) {
			var nom =_eqLogic.configuration.agendas[i]['nom'];
			var agenda_id= _eqLogic.configuration.agendas[i]['agenda_id'];
			var checked=_eqLogic.configuration.agendas[i]['checked'];
			if (checked == "1"){
				html += '<label  class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" id="" data-l1key="agendas" agenda_id="'+agenda_id+'" nom="'+nom+'" checked/>'+nom+'</label>'; 
			}else{
				html += '<label  class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" id="" data-l1key="agendas" agenda_id="'+agenda_id+'" nom="'+nom+'"  />'+nom+'</label>'; 
			}
		}
		html += '</div>' ;
		$('#div_listCalendar').append(html);
	}
	if (_eqLogic.configuration.type_equipement == 'filtre') {
		$('#div_type_agenda').hide();
		$('#div_action_debut').empty();
		$('#div_action_fin').empty();
		$('#filtre').show();
		$('#div_listCalendar_filtre').empty();
		$('#div_Type_filtre').show();
		if (isset(_eqLogic.configuration)) {
			$('input[id=filtre]').val(_eqLogic.configuration.filtre);
			$('input[id=sur_contenu]').prop( "checked", _eqLogic.configuration.sur_contenu );
			$('input[id=sur_titre]').prop( "checked", _eqLogic.configuration.sur_titre );
			if (_eqLogic.configuration.equipement == "" ){
				$('#label_recherche').hide();
			}else{
				$('#label_recherche').show();
				$("#equipement").val(_eqLogic.configuration.equipement);
			}
			if (isset(_eqLogic.configuration.action_debut)) {
				for (var i in _eqLogic.configuration.action_debut) {
					addEvent(_eqLogic.configuration.action_debut[i], 'action_debut', '{{Action}}');
				}
			}
			if (isset(_eqLogic.configuration.action_fin)) {
				for (var i in _eqLogic.configuration.action_fin) {
					addEvent(_eqLogic.configuration.action_fin[i], 'action_fin', '{{Action}}');
				}
			}	
			
			jeedom.cmd.displayActionsOption({
				params : actionOptions,
				async : false,
				error: function (error) {
					$('#div_alert').showAlert({message: error.message, level: 'danger'});
				},
				success : function(data){
					for(var i in data){
						$('#'+data[i].id).append(data[i].html.html);
					}
					taAutosize();
				}
			});	
			
			
			
		}
	}

}
function saveEqLogic(_eqLogic) {
    if (!isset(_eqLogic.configuration)) {
        _eqLogic.configuration = {};
    }
   if (_eqLogic.configuration.type_equipement == 'agenda') {
		_eqLogic.configuration.client_id=$('input[id=client_id]').val();
		_eqLogic.configuration.client_secret=$('input[id=client_secret]').val();
		var agendas=[];
		$('#div_listCalendar input:checkbox').each(function () {
			var agenda={};
			agenda['nom']=this.attributes["nom"].value;
			agenda['agenda_id']=this.attributes["agenda_id"].value;
			agenda['checked']=this.checked;
			agendas.push(agenda);
		});
		_eqLogic.configuration.agendas = agendas;
  }else if (_eqLogic.configuration.type_equipement == 'filtre') {
	_eqLogic.configuration.filtre = $('input[id=filtre]').val();
	_eqLogic.configuration.sur_contenu = $('input[id=sur_contenu]').prop('checked');
	_eqLogic.configuration.sur_titre = $('input[id=sur_titre]').prop('checked');
	_eqLogic.configuration.action_debut = $('#div_action_debut .action_debut').getValues('.expressionAttr');
	_eqLogic.configuration.action_fin = $('#div_action_fin .action_fin').getValues('.expressionAttr');
	_eqLogic.configuration.equipement = $('.eqLogic .eqLogicAttr[data-l1key=equipement]').value();
	}
	return _eqLogic;
}
$('#bt_MAJ_agendas').on('click', function() {
	 $.ajax({
		type: "POST", 
		url: "plugins/Google_agenda/core/ajax/Google_agenda.ajax.php", 
		data: {
			action: "get_eqlogic",
			id: $('.eqLogic .eqLogicAttr[data-l1key=id]').value()
		},
		dataType: 'json',
		error: function (request, status, error) {
			handleAjaxError(request, status, error);
		},
		success: function (data) {
			if (data.state != 'ok') {
				$('#div_alert').showAlert({message: data.result, level: 'danger'});
				return;
			}
			updateCalendarList(data.result);
		}
	});
	
});

function updateCalendarList(_eqLogic){
	var eqLogic_agendas = _eqLogic.configuration.agendas
	var agendas_source=[];
	var agendas=[];
 $.ajax({
    type: "POST", 
    url: "plugins/Google_agenda/core/ajax/Google_agenda.ajax.php", 
    data: {
        action: "listCalendar",
        id: $('.eqLogic .eqLogicAttr[data-l1key=id]').value()
    },
    dataType: 'json',
	async: false,
    error: function (request, status, error) {
        handleAjaxError(request, status, error);
    },
    success: function (data) {
        if (data.state != 'ok') {
            $('#div_alert').showAlert({message: data.result, level: 'danger'});
            return;
        }
		if (data.result == 'Nok') {
            $('#div_alert').showAlert({message: "Impossible de récuperer la liste des calendiers, vous n'êtes probablement pas connecté à internet...", level: 'danger'});
            return;
        }
		data.result.forEach(function(agenda){agendas_source.push(agenda);})
  }
});
		html = '<div class="list_calendars">';
		agendas_source.forEach(function(agenda_source){
			var existe=false
			if (typeof(eqLogic_agendas) === 'object'){
				eqLogic_agendas.forEach(function(agenda){
					var agenda_id= agenda.agenda_id;
					var checked=agenda.checked;
					if(agenda_source.id == agenda_id ){
						existe=true
						if (checked =="1") {
							html += '<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr"  data-l1key="agendas" agenda_id="'+agenda_source.id+'" nom="'+agenda_source.summary+'" checked />'+agenda_source.summary+'</label>'; 
						}else{
							html += '<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr"  data-l1key="agendas" agenda_id="'+agenda_source.id+'" nom="'+agenda_source.summary+'"  />'+agenda_source.summary+'</label>'; 
						}
					}
				})
			}
			if (!existe){
				html += '<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="agendas" agenda_id="'+agenda_source.id+'" nom="'+agenda_source.summary+'"  />'+agenda_source.summary+'</label>'; 
			}
		})	
		html += '</div>' ;
		$('#div_listCalendar').empty();
		$('#div_listCalendar').append(html);
	  
		
		var _eqLogic_save=saveEqLogic(_eqLogic)
		jeedom.eqLogic.save({
			type: eqType,
			eqLogics: [_eqLogic_save],
			error: function (error) {
				$('#div_alert').showAlert({message: error.message, level: 'danger'});
			},
			success: function (data) {
				$('#div_alert').showAlert({message: "Sauvegarde effectuée avec succès", level: 'success'});
			}
		});
			

}

$('.eqLogicAction[data-action=ajout_filtre]').on('click', function () {
    bootbox.prompt("{{Nom du filtre?}}", function (result) {
        if (result !== null) {
			$.ajax({// fonction permettant de faire de l'ajax
				type: "POST", // methode de transmission des données au fichier php
				url: "plugins/Google_agenda/core/ajax/Google_agenda.ajax.php", 
				data: {
					action: "ajout_filtre",
					name: result
				},
				dataType: 'json',
				error: function(request, status, error) {
					handleAjaxError(request, status, error);
				},
				success: function(data) { // si l'appel a bien fonctionné
					if (data.state != 'ok') {
						$('#div_alert').showAlert({message:  data.result,level: 'danger'});
						return;
					}
					modifyWithoutSave=false;
				   window.location.reload();					
				}
			});			
        }
    });
});

$('.eqLogicAction[data-action=ajout_agenda]').on('click', function () {
	
    bootbox.prompt("{{Nom de l'agenda?}}", function (result) {
        if (result !== null) {
			$.ajax({// fonction permettant de faire de l'ajax
				type: "POST", // methode de transmission des données au fichier php
				url: "plugins/Google_agenda/core/ajax/Google_agenda.ajax.php", 
				data: {
					action: "ajout_agenda",
					name: result
				},
				dataType: 'json',
				error: function(request, status, error) {
					handleAjaxError(request, status, error);
				},
				success: function(data) { // si l'appel a bien fonctionné
					if (data.state != 'ok') {
						$('#div_alert').showAlert({message:  data.result,level: 'danger'});
						return;
					}
					modifyWithoutSave=false;
				   window.location.reload();					
				}
			});			
        }
    });
});

$('#Ajout_action_debut').on('click', function() {
    addEvent({},'action_debut', '{{Action}}');
});

$('#Ajout_action_fin').on('click', function() {
    addEvent({},'action_fin', '{{Action}}');
});

$('body').delegate('.action_debut .expressionAttr[data-l1key=cmd]', 'focusout', function (event) {
  	var el = $(this);
 	var expression = el.closest('.action_debut').getValues('.expressionAttr');
    jeedom.cmd.displayActionOption(el.value(), init(expression[0].options), function (html) {
      el.closest('.action_debut').find('.actionOptions').html(html);
    });
});

$('body').delegate('.action_fin .expressionAttr[data-l1key=cmd]', 'focusout', function (event) {
  	var el = $(this);
  	var expression = el.closest('.action_fin').getValues('.expressionAttr');
    jeedom.cmd.displayActionOption(el.value(), init(expression[0].options), function (html) {
      el.closest('.action_fin').find('.actionOptions').html(html);
    });
});

$("body").delegate(".listCmdAction", 'click', function() {
    var type = $(this).attr('data-type');
    var el = $(this).closest('.' + type).find('.expressionAttr[data-l1key=cmd]');
    jeedom.cmd.getSelectModal({cmd: {type: 'action'}}, function(result) {
        el.value(result.human);
        jeedom.cmd.displayActionOption(el.value(), '', function(html) {
            el.closest('.' + type).find('.actionOptions').html(html);
        });
    });
});

 $("body").delegate(".listAction", 'click', function () {
  var type = $(this).attr('data-type');
  var el = $(this).closest('.' + type).find('.expressionAttr[data-l1key=cmd]');
  jeedom.getSelectActionModal({}, function (result) {
    el.value(result.human);
    jeedom.cmd.displayActionOption(el.value(), '', function (html) {
      el.closest('.' + type).find('.actionOptions').html(html);
      taAutosize();
  });
});
});

$("body").delegate(".listEquipement", 'click', function() {
    var type = $(this).attr('data-type');
    var el = $(this).closest('.' + type).find('.expressionAttr[data-l1key=eqLogic]');
    jeedom.eqLogic.getSelectModal({}, function(result) {
    	el.value(result.human);
    });
});

$("body").delegate('.bt_removeAction', 'click', function() {
    var type = $(this).attr('data-type');
    $(this).closest('.' + type).remove();
});
function addEvent(_action,_type, _name, _el,id) {
	
    if (!isset(_action)) {
        _action = {};
    }
    if (!isset(_action.options)) {
        _action.options = {};
    }


    var div = '<div class="' + _type + '">';
	//div += '<div class="form-group ">';
	var actionOption_id = uniqId();

	
	
    div += '<div class="form-group ">';
	div += '<div>';
	if(_type == "action_debut"){
		div += "<label class='col-sm-4 checkbox-inline'>Moment du déclenchement</label>";
		div += "<select class='expressionAttr form-control col-sm-5' data-l1key='moment'>";
		div += "<option value='heure'>A l'heure du début l'évènement";
		div += "<option value='jour'>Au début de la journée";
		div += "</select>";
		//div += '</div>';
	}else{
		div += "<label class='col-sm-4 checkbox-inline'>Moment du déclenchement</label>";
		div += "<select class='expressionAttr form-control col-sm-5' data-l1key='moment'>";
		div += "<option value='heure'>A l'heure de fin de l'évènement";
		div += "<option value='jour'>A la fin de la journée";
		div += "</select>";
		//div += '</div>';
	}
   // div += '<label class="col-sm-1 control-label"></label>';
    div += '<div class="col-sm-4">';
    div += '<div class="input-group">';
 	div += '<span class=" input-sm input-group-addon roundedLeft"style="width: 100px">' + _name + '</span>';
	div += '</span>';
    div += '<input class="expressionAttr form-control input-sm cmdAction" data-l1key="cmd" data-type="' + _type + '" />';
    div += '<span class="input-group-btn">';
    div += '<a class="btn btn-success btn-sm listAction" data-type="' + _type + '" title="{{Sélectionner un mot-clé}}"><i class="fa fa-tasks"></i></a>';
    div += '<a class="btn btn-success btn-sm listCmdAction" data-type="' + _type + '"><i class="fa fa-list-alt"></i></a>';
	div += '<a class="btn btn-default bt_removeAction btn-sm" data-type="' + _type + '"><i class="fa fa-minus-circle"></i></a>';
    div += '</span>';
    div += '</div>';
    div += '</div>';
    
    div += '<div class="col-sm-5 actionOptions" id="'+actionOption_id+'">';
    div += '</div>';
    div += '</div>';
    if (isset(_el)) {
        _el.find('.div_' + _type).append(div);
        _el.find('.' + _type + ':last').setValues(_action, '.expressionAttr');
    } else {
        $('#div_' + _type).append(div);
        $('#div_' + _type + ' .' + _type + ':last').setValues(_action, '.expressionAttr');
    }
    actionOptions.push({
        expression : init(_action.cmd, ''),
        options : _action.options,
        id : actionOption_id
    });
}