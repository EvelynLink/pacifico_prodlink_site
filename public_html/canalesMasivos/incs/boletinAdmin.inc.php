<? if ($Central->conPermiso("Boletines Electronicos,Administrador")) { 
    require_once("../boletines/classes/class.boContenido.php"); 
    $a = new boContenido();?>
<div align="center" class="PARRAFOMAIN" style="border:1px solid gray;width:800px;padding:5px;background-color:white;color:gray;">
<br>
  <?
  if($esCapturada){
	echo "<br>".$lang["Termine de editar esta sección para poder enviarla"]."<br><br>&nbsp;";
  }else{
  ?>
  <table width="90%" border="0" cellspacing="0" cellpadding="4">
      
  <form action="<? echo $_SERVER["PHP_SELF"]."?c=".$Ancestros[0]->id;?>" method="post" name="newsletterPanel">
  <input align="center" name="newsletterAction" type="hidden" value="">
  <tr valign="top"> 
<!--	    <td width="43%" align="right" style="border-bottom:1px solid #aabbaa;color:black;"> 
		  <b><? /*echo $lang["Send Newsletter to these emails"]; */?></b><br>
		  <?/* echo $lang["Escriba un email por línea"]; */?><a href="javascript:void(0);" onClick="if($('ayuda_bol_1').style.display=='none'){$('ayuda_bol_1').style.display='';}else{$('ayuda_bol_1').style.display='none';}"><img src="../comunes/images/pregunta.gif" border='0'/></a>
		  <div id='ayuda_bol_1' style='display:none;border:1px dotted gray;background:#FFFFEE;padding:3px;font-size:80%;text-align:left;'>
		  <? /*echo $lang["ejemplos correctos boletin"]; */?>
		  </div>
		</td>-->
<!--	  <td width="43%" style="border-bottom:1px solid #aabbaa;"> <textarea name="newsletterCorreos" cols="40" rows="6"></textarea></td>-->
	  <td align="center" width="100%" valign="bottom" style="text-align:center;"> <input type="button" name="Button1" value="<? echo $lang["Guardar e ir a grupos de envío"];?>" onClick="document.newsletterPanel.newsletterAction.value='Lista';document.newsletterPanel.submit();"></td>
	</tr>
	<!--<tr valign="top"> 
	    <td width="43%" align="right" style="border-bottom:1px solid #aabbaa;color:black;"> 
		  <b><? /*echo $lang["Send Newsletter to these emails"]; */?></b><br>
		  <? /*echo $lang["Escriba un email por línea"]; */?><a href="javascript:void(0);" onClick="if($('ayuda_bol_1').style.display=='none'){$('ayuda_bol_1').style.display='';}else{$('ayuda_bol_1').style.display='none';}"><img src="../comunes/images/pregunta.gif" border='0'/></a>
		  <div id='ayuda_bol_1' style='display:none;border:1px dotted gray;background:#FFFFEE;padding:3px;font-size:80%;text-align:left;'>
		  <? /*echo $lang["ejemplos correctos boletin"]; */?>
		  </div>
		</td>
	  <td width="43%" style="border-bottom:1px solid #aabbaa;"> <textarea name="newsletterCorreos" cols="40" rows="6"></textarea></td>
	  <td width="14%" valign="bottom" style="border-bottom:1px solid #aabbaa;"> <input type="button" name="Button1" value="<? /*echo $lang["Guardar e ir a grupos de envío"];*/?>" onClick="if(document.newsletterPanel.newsletterCorreos.value!=''){document.newsletterPanel.newsletterAction.value='Lista';document.newsletterPanel.submit();}"></td>
	</tr>-->
<!--	<tr valign="top"> 
	  <td colspan="3" align="right" style="border-bottom:1px solid #aabbaa;">&nbsp;</td>
	</tr>-->
<!--	<tr valign="top"> 
	    <td colspan="2" align="right" style="border-bottom:1px solid #aabbaa;color:black;"><b><? /*echo $lang["Send Newsletter to"]; */ ?>:</b><br>
		<? /*
		$db->query("SELECT * FROM gruposcorreo 
		WHERE gruposcorreo_publico='Y'
		ORDER BY gruposcorreo_nombre ASC");
		while ($row=$db->fetchRow()){
			echo "$row[gruposcorreo_nombre]<input type='checkbox' name='newsletterGruposDeCorreo$row[gruposcorreo_id]' value='$row[gruposcorreo_id]'><br>\n";
		}
		echo "-------------------<br>";
		$db->query("SELECT * FROM gruposcorreo 
		WHERE gruposcorreo_publico='N'
		ORDER BY gruposcorreo_nombre ASC");
		while ($row=$db->fetchRow()){
			echo "$row[gruposcorreo_nombre]<input type='checkbox' name='newsletterGruposDeCorreo$row[gruposcorreo_id]' value='$row[gruposcorreo_id]'><br>\n";
		}
		*/
		?>
		</td>
	  <td valign="bottom" style="border-bottom:1px solid #aabbaa;">
	  <input type="button" name="Button2" value="<? /*echo $lang["Submit"];?>" onClick="if(confirm('<?
	  echo $lang["Está seguro de querer enviar los contenidos de esta sección como boletín a los grupos de correo seleccionados?"]; */
	  ?>')){document.newsletterPanel.newsletterAction.value='Todos';document.newsletterPanel.submit();}"> </td>
	</tr>-->
<!--	<tr valign="top"> 
	  <td colspan="3" align="center" style="color:black;">
	  <input type="button" name="Button3" value="<? /*echo $lang["Administer newsletter users and groups"];*/?>" onClick="window.location='../boletines/editNewsletterUG.php';"></td>
	</tr>-->
	</form>
</table>
	<?
	}
	?>
</div>
<? } ?>
<? //_FIN_DE_ARCHIVO ?>