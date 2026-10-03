				<div class="tab-pane" id="tab-ticketbai">
					<table class="table table-bordered table-striped table-hover info-page">
						<tr>
							<td class="col-sm-3"><?php echo $text_info_sent_date; ?></td>
							<td id="tbai-sent-date"><?php echo $tbai_sent_date; ?></td>
						</tr>
						<tr>
							<td><?php echo $text_info_status; ?></td>
							<td id="tbai-status"><?php echo $tbai_status; ?></td>
						</tr>
						<tr>
							<td><?php echo $text_info_notice; ?></td>
							<td id="tbai-notice"><?php echo $tbai_notice; ?></td>
						</tr>
						<tr>
							<td><?php echo $text_info_identifier; ?></td>
							<td id="tbai-identifier"><?php echo $tbai_identifier; ?></td>
						</tr>
					</table>
					<button type="button" id="button-tbai-resend" class="btn btn-primary"><svg class="bi" aria-hidden="true"><use href="view/image/bootstrap-icons.svg#send"/></svg> <?php echo $button_resend_invoice; ?></button>
				</div>
