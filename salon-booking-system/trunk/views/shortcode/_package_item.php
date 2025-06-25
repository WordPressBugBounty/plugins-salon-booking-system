<label for="sln_packages_180" class="sln-list__item sln-package sln-package--180">
	<?php
    /*
    bunch of html to include if there is a thumb to show.
    */
    // phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
    if ($x == 'thumb'): ?>
	<div class="sln-list__item__thumb">
		<img loading="lazy" decoding="async" width="150" height="150" src="http://salon.local/wp-content/uploads/2022/04/shave-150x150.jpg" class="attachment-thumbnail size-thumbnail wp-post-image" alt="" srcset="http://salon.local/wp-content/uploads/2022/04/shave-150x150.jpg 150w, http://salon.local/wp-content/uploads/2022/04/shave-298x300.jpg 298w, http://salon.local/wp-content/uploads/2022/04/shave.jpg 500w" sizes="(max-width: 150px) 100vw, 150px">
	</div>
	<?php
    endif; 
    /*
    bunch of html to include if there is a thumb to show. // END
    */
    ?>
    <div class="sln-list__item__content">
        <div class="sln-list__item__subcontent">
            <h3 class="sln-package-name sln-list__item__name">Monthly massages pack</h3>
            <!-- THIS COLLAPSIBLE TEXT BLOCK IS THE SAME AS IN THE SERVICES ITEM  -->
            <p class="sln-package-description sln-list__item__description sln-list__item__description__toggle">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer fringilla sollicitudin sapien a finibus. Duis fringilla lobortis aliquam. <span class="sln-list__item__description__breakdots">...</span> <span class="sln-list__item__description__more"> Cras volutpat risus metus, ut varius nulla pulvinar ut.</span></p>
        </div>
        <div class="sln-package__info sln-list__item__info">
        	<h3 class="sln-package__price sln-list__item__price">
        		<span class="sln-package-price-value sln-list__item__proce__value"><strong>$300</strong></span>
                    <!-- .sln-package-price // END -->
         	</h3>
         	<h5 class="sln-package__price__note sln-list__item__price__note">One time purchase / to be consumed by a month</h5>
    	 </div>
    </div>
    <div class="sln-list__item__morecontent sln-list__item__morecontent--packages">
        <a class="sln-list__item__morecontent__trigger">
            <span class="sln-list__item__morecontent__trigger--show"><?php esc_html_e('Discover included services','salon-booking-system'); ?></span>
            <span class="sln-list__item__morecontent__trigger--hide"><?php esc_html_e('Hide included services','salon-booking-system'); ?></span>
        </a>
        <div class="sln-list__item__morecontent__panel collapse">
            <ul class="sln-package__services">
                <!-- ITEM TO LOOP -->
                <li class="sln-package__service">
                    <span class="sln-package__service__count">4</span>
                    <span class="sln-package__service__divider">-</span>
                    <span class="sln-package__service__title">Deep tissue massage from</span>
                </li>
                <!-- ITEM TO LOOP // END -->
                <li class="sln-package__service">
                    <span class="sln-package__service__count">2</span>
                    <span class="sln-package__service__divider">-</span>
                    <span class="sln-package__service__title">Neck massage from</span>
                </li>
            </ul>
        </div>
    </div>
        
<div class="sln-package__action sln-list__item__action">
    <div class="sln-checkbox">
                <input type="checkbox" name="sln[packages][180]" id="sln_packages_180" value="1" data-price="29.99" data-duration="0">
            <label for="sln_packages_180"></label>
    </div>
</div>
<span class="sln-package__errors sln-list__item__errors errors-area" data-class="sln-alert sln-alert-medium sln-alert--problem"></span>
    <div class="sln-alert sln-alert-medium sln-alert--problem" style="display: none" id="availabilityerror">Not enough time for this package</div>
    <div class="sln-list__item__fkbkg"></div>
</label>