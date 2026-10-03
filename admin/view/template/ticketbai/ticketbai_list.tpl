<?php echo $header; ?>

<?php include(DIR_TEMPLATE . 'common/template-header.tpl'); ?>

<?php foreach ($warnings as $tbai_warning) { ?>
<div class="alert alert-warning"><?php echo $tbai_warning; ?></div>
<?php } ?>

<div class="card page-card">

	<div class="card-header clearfix">
		<div class="float-start h2"><i class="fa fa-qrcode"></i> <?php echo $heading_title; ?></div>
		<div class="float-end">
			<a href="<?php echo $setting; ?>" class="btn btn-primary"><i class="fa fa-cog"></i> <?php echo $button_setting; ?></a>
		</div>
	</div>

	<div class="card-body">
		<?php if ($active) { ?>
		<p class="text-muted"><?php echo $text_active_note; ?></p>
		<?php } ?>
		<div id="tbai-alert"></div>
		<div class="table-responsive">
			<table class="table table-bordered table-striped table-hover">
				<thead>
					<tr>
						<td><?php echo $column_invoice; ?></td>
						<td><?php echo $column_type; ?></td>
						<td><?php echo $column_customer; ?></td>
						<td><?php echo $column_date; ?></td>
						<td class="text-end"><?php echo $column_total; ?></td>
						<td><?php echo $column_identifier; ?></td>
						<td><?php echo $column_environment; ?></td>
						<td><?php echo $column_status; ?></td>
						<td class="text-end"><?php echo $column_action; ?></td>
					</tr>
					<tr id="filter">
						<td colspan="7"></td>
						<td>
							<select name="filter_status" class="form-select">
								<option value=""><?php echo $text_all; ?></option>
								<?php foreach ($statuses as $status_code => $status_name) { ?>
								<option value="<?php echo $status_code; ?>"<?php echo ($status_code == $filter_status) ? ' selected="selected"' : ''; ?>><?php echo $status_name; ?></option>
								<?php } ?>
							</select>
						</td>
						<td class="text-end"><button type="button" id="button-tbai-filter" class="btn btn-default"><i class="fa fa-filter"></i> <?php echo $button_filter; ?></button></td>
					</tr>
				</thead>
				<tbody>
					<?php if ($records) { ?>
					<?php foreach ($records as $record) { ?>
					<tr>
						<td><a href="<?php echo $record['invoice']; ?>"><?php echo $record['number']; ?></a></td>
						<td><?php echo $record['type']; ?></td>
						<td><?php echo $record['customer']; ?></td>
						<td><?php echo $record['date']; ?></td>
						<td class="text-end"><?php echo $record['total']; ?></td>
						<td><small><?php echo $record['identifier']; ?></small></td>
						<td><?php echo $record['environment']; ?></td>
						<td>
							<span class="badge <?php echo ($record['status'] == 'sent') ? 'bg-success text-white' : (($record['status'] == 'signed') ? 'bg-warning text-dark' : 'bg-danger text-white'); ?>"><?php echo $record['status_text']; ?></span>
							<?php if ($record['message']) { ?>
							<div><small><?php echo $record['message']; ?></small></div>
							<?php } ?>
						</td>
						<td class="text-end" style="white-space:nowrap;">
							<?php if ($can_modify && $record['can_resend']) { ?>
							<button type="button" class="btn btn-primary tbai-resend" data-invoice-id="<?php echo $record['invoice_id']; ?>"><i class="fa fa-paper-plane"></i> <?php echo $button_resend; ?></button>
							<?php } ?>
							<a href="<?php echo $record['xml']; ?>" class="btn btn-default"><i class="fa fa-download"></i> <?php echo $button_xml; ?></a>
						</td>
					</tr>
					<?php } ?>
					<?php } else { ?>
					<tr>
						<td class="text-center" colspan="9"><?php echo $text_no_results; ?></td>
					</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
		<div class="pagination"><?php echo $pagination; ?></div>
	</div>
</div>

<script type="text/javascript"><!--
$('#button-tbai-filter').on('click', function() {
	var url = '<?php echo $filter_action; ?>';
	var status = $('select[name=\'filter_status\']').val();

	if (status) {
		url += '&filter_status=' + encodeURIComponent(status);
	}

	location = url;
});

$('.tbai-resend').on('click', function() {
	if (!confirm('<?php echo addslashes(html_entity_decode($text_confirm_resend, ENT_QUOTES, 'UTF-8')); ?>')) {
		return;
	}

	var $button = $(this);

	$.ajax({
		url: '<?php echo $resend_url; ?>',
		type: 'post',
		data: {invoice_id: $button.data('invoice-id')},
		dataType: 'json',
		beforeSend: function() {
			$button.prop('disabled', true);
		},
		complete: function() {
			$button.prop('disabled', false);
		},
		success: function(json) {
			var $alert = $('<div class="alert"></div>').addClass(json['success'] ? 'alert-success' : 'alert-danger').html(json['success'] ? json['success'] : json['error']);

			$('#tbai-alert').empty().append($alert);

			if (json['success']) {
				setTimeout(function() { location.reload(); }, 1200);
			}
		},
		error: function(xhr, ajaxOptions, thrownError) {
			$('#tbai-alert').html('<div class="alert alert-danger"></div>').find('.alert').text(thrownError);
		}
	});
});
//--></script>

<?php echo $footer; ?>
