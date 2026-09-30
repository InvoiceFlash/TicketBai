<?php echo $header; ?>

<?php include(DIR_TEMPLATE . 'common/template-header.tpl'); ?>

<div class="panel panel-default">

	<div class="panel-heading clearfix">
		<div class="pull-left h2"><i class="fa fa-cog"></i> <?php echo $heading_title; ?></div>
		<div class="pull-right">
			<button type="submit" form="form-ticketbai" class="btn btn-primary"><i class="fa fa-save"></i> <?php echo $button_save; ?></button>
			<a href="<?php echo $cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i> <?php echo $button_cancel; ?></a>
		</div>
	</div>

	<div class="panel-body">
		<?php if ($requirements) { ?>
		<div class="alert alert-warning"><?php echo $requirements; ?></div>
		<?php } else { ?>
		<div class="alert alert-success"><?php echo $text_requirements_ok; ?></div>
		<?php } ?>

		<form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-ticketbai">
			<div class="form-group row">
				<label class="col-sm-3 control-label" for="input-active"><?php echo $entry_active; ?></label>
				<div class="col-sm-9">
					<select name="ticketbai_active" id="input-active" class="form-control">
						<option value="0"<?php echo !$ticketbai_active ? ' selected="selected"' : ''; ?>><?php echo $text_no; ?></option>
						<option value="1"<?php echo $ticketbai_active ? ' selected="selected"' : ''; ?>><?php echo $text_yes; ?></option>
					</select>
					<small class="text-muted"><?php echo $text_verifactu_note; ?></small>
				</div>
			</div>

			<div class="form-group row">
				<label class="col-sm-3 control-label" for="input-territory"><?php echo $entry_territory; ?></label>
				<div class="col-sm-9">
					<select name="ticketbai_territory" id="input-territory" class="form-control">
						<option value=""></option>
						<option value="01"<?php echo ($ticketbai_territory == '01') ? ' selected="selected"' : ''; ?>><?php echo $text_araba; ?></option>
						<option value="02"<?php echo ($ticketbai_territory == '02') ? ' selected="selected"' : ''; ?>><?php echo $text_bizkaia; ?></option>
						<option value="03"<?php echo ($ticketbai_territory == '03') ? ' selected="selected"' : ''; ?>><?php echo $text_gipuzkoa; ?></option>
					</select>
				</div>
			</div>

			<div class="form-group row">
				<label class="col-sm-3 control-label" for="input-environment"><?php echo $entry_environment; ?></label>
				<div class="col-sm-9">
					<select name="ticketbai_environment" id="input-environment" class="form-control">
						<option value="test"<?php echo ($ticketbai_environment != 'production') ? ' selected="selected"' : ''; ?>><?php echo $text_test; ?></option>
						<option value="production"<?php echo ($ticketbai_environment == 'production') ? ' selected="selected"' : ''; ?>><?php echo $text_production; ?></option>
					</select>
				</div>
			</div>

			<div class="form-group row">
				<label class="col-sm-3 control-label" for="input-license"><?php echo $entry_license; ?></label>
				<div class="col-sm-9">
					<input type="text" name="ticketbai_license" id="input-license" value="<?php echo htmlspecialchars($ticketbai_license, ENT_QUOTES, 'UTF-8'); ?>" maxlength="20" class="form-control" />
					<?php if ($error_license) { ?>
					<div class="text-danger"><?php echo $error_license; ?></div>
					<?php } ?>
					<small class="text-muted"><?php echo $text_license_note; ?></small>
				</div>
			</div>

			<div class="form-group row">
				<label class="col-sm-3 control-label" for="input-developer-nif"><?php echo $entry_developer_nif; ?></label>
				<div class="col-sm-9">
					<input type="text" name="ticketbai_developer_nif" id="input-developer-nif" value="<?php echo htmlspecialchars($ticketbai_developer_nif, ENT_QUOTES, 'UTF-8'); ?>" maxlength="9" class="form-control" />
					<?php if ($error_developer_nif) { ?>
					<div class="text-danger"><?php echo $error_developer_nif; ?></div>
					<?php } ?>
					<small class="text-muted"><?php echo $text_certificate_note; ?></small>
				</div>
			</div>

			<div class="form-group row">
				<label class="col-sm-3 control-label" for="input-self-employed"><?php echo $entry_self_employed; ?></label>
				<div class="col-sm-9">
					<select name="ticketbai_self_employed" id="input-self-employed" class="form-control">
						<option value="0"<?php echo !$ticketbai_self_employed ? ' selected="selected"' : ''; ?>><?php echo $text_no; ?></option>
						<option value="1"<?php echo $ticketbai_self_employed ? ' selected="selected"' : ''; ?>><?php echo $text_yes; ?></option>
					</select>
				</div>
			</div>

			<div class="form-group row">
				<label class="col-sm-3 control-label" for="input-epigraph"><?php echo $entry_epigraph; ?></label>
				<div class="col-sm-9">
					<input type="text" name="ticketbai_epigraph" id="input-epigraph" value="<?php echo htmlspecialchars($ticketbai_epigraph, ENT_QUOTES, 'UTF-8'); ?>" maxlength="7" class="form-control" />
				</div>
			</div>

			<div class="form-group row">
				<label class="col-sm-3 control-label" for="input-exempt-reason"><?php echo $entry_exempt_reason; ?></label>
				<div class="col-sm-9">
					<select name="ticketbai_exempt_reason" id="input-exempt-reason" class="form-control">
						<?php foreach ($exempt_reasons as $reason_code => $reason_name) { ?>
						<option value="<?php echo $reason_code; ?>"<?php echo ($ticketbai_exempt_reason == $reason_code) ? ' selected="selected"' : ''; ?>><?php echo $reason_name; ?></option>
						<?php } ?>
					</select>
				</div>
			</div>
			<div class="form-group row">
				<label class="col-sm-3 control-label" for="input-foreign-operation"><?php echo $entry_foreign_operation; ?></label>
				<div class="col-sm-9">
					<select name="ticketbai_foreign_operation" id="input-foreign-operation" class="form-control">
						<option value="services"<?php echo ($ticketbai_foreign_operation != 'delivery') ? ' selected="selected"' : ''; ?>><?php echo $text_services; ?></option>
						<option value="delivery"<?php echo ($ticketbai_foreign_operation == 'delivery') ? ' selected="selected"' : ''; ?>><?php echo $text_delivery; ?></option>
					</select>
				</div>
			</div>
		</form>
	</div>
</div>

<?php echo $footer; ?>
