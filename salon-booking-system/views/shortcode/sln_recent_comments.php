<?php
if(!$data['comments']) return;
$plugin = SLN_Plugin::getInstance();
?>
<section class="sln-datashortcode sln-datashortcode--comments">
    <div class="sln-datalist sln-datalist--styled sln-datalist--1cols">
	<?php
	foreach ($data['comments'] as $comment) {
        if($comment->comment_approved) {
        ?>
                <div class="sln-datalist__item">
                    <div class="sln-datalist__item__author">
                            <?php $user = get_user_by('email', $comment->comment_author_email) ?>
                            <?php echo $user ? esc_html($user->first_name) . ' ' . esc_html($data['truncate_lastname'] ? substr($user->last_name, 0, 1) : $user->last_name) : esc_html($comment->comment_author); ?>
                    </div>
                        <p class="sln-datalist__item__date">
                            <?php echo esc_html(gmdate('d.m.Y', strtotime($comment->comment_date))) ?>
			</p>
                        <?php if ($comment->rating) { ?>
                            <span class="sln-datalist__item__rating">
                                <input type="hidden" name="sln-rating" value="<?php echo esc_html($comment->rating); ?>">
                                <span class="rating"></span>
                                <span class="rating-value"><?php echo esc_html($comment->rating) ?>/5</span>
                            </span>
                        <?php } ?>
                        <p class="sln-datalist__item__comment">
                            <?php echo esc_html($comment->comment_content) ?>
			</p>
		</div>
	<?php }
    } ?>
		<div class="sln-datalist_clearfix"></div>
	</div>
</section>
