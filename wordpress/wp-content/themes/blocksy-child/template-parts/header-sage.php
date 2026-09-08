<?php
/** Approved ivory and sage header. Included by the guarded MU loader. */
namespace BactivePH\SageHeader;
defined('ABSPATH') || exit;
$cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/');
$account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');
?>
<?php if ($device === 'desktop') : ?>
<div class="bactive-header__desktop">
    <div class="bactive-header__brand"><?php echo $logo; /* Blocksy's configured logo renderer. */ ?></div>
    <nav class="bactive-header__primary" aria-label="Primary navigation">
        <details class="bactive-header__shop bactive-header__disclosure">
            <summary>Shop <?php icon('chevron'); ?></summary>
            <div class="bactive-header__dropdown">
                <?php foreach (links('collections') as $label => $path) : ?>
                <a href="<?php echo esc_url(home_url($path)); ?>"<?php echo current_attribute($path); ?>><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </div>
        </details>
        <?php foreach (links('primary') as $label => $path) : ?>
        <a href="<?php echo esc_url(home_url($path)); ?>"<?php echo current_attribute($path); ?>><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="bactive-header__utilities">
        <details class="bactive-header__search bactive-header__disclosure">
            <summary class="bactive-header__utility"><?php icon('search'); ?><span>Search</span></summary>
            <div class="bactive-header__search-panel"><?php search_form('desktop'); ?></div>
        </details>
        <a class="bactive-header__utility" href="<?php echo esc_url($account_url); ?>"><?php icon('account'); ?><span>Account</span></a>
        <a class="bactive-header__utility" href="<?php echo esc_url($cart_url); ?>"><?php icon('bag'); ?><span>Bag</span></a>
    </div>
</div>
<?php else : ?>
<div class="bactive-header__mobile">
    <div class="bactive-header__brand"><?php echo $logo; /* Blocksy's configured logo renderer. */ ?></div>
    <a class="bactive-header__mobile-bag" href="<?php echo esc_url($cart_url); ?>" aria-label="Shopping bag"><?php icon('bag'); ?></a>
    <details class="bactive-header__mobile-menu">
        <summary class="bactive-header__menu-toggle"><span class="bactive-header__menu-open"><?php icon('menu'); ?><span class="bactive-header__sr-only">Open menu</span></span><span class="bactive-header__menu-close"><?php icon('close'); ?><span class="bactive-header__sr-only">Close menu</span></span></summary>
        <nav class="bactive-header__mobile-panel" aria-label="Mobile navigation">
            <details class="bactive-header__collections" open>
                <summary>Shop <?php icon('chevron'); ?></summary>
                <div class="bactive-header__collection-links">
                    <?php foreach (links('collections') as $label => $path) : ?>
                    <a href="<?php echo esc_url(home_url($path)); ?>"<?php echo current_attribute($path); ?>><?php echo esc_html($label); ?></a>
                    <?php endforeach; ?>
                </div>
            </details>
            <div class="bactive-header__mobile-primary">
                <?php foreach (links('primary') as $label => $path) : ?>
                <a href="<?php echo esc_url(home_url($path)); ?>"<?php echo current_attribute($path); ?>><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </div>
            <a class="bactive-header__mobile-account" href="<?php echo esc_url($account_url); ?>">My account</a>
            <?php search_form('mobile'); ?>
        </nav>
    </details>
</div>
<?php endif; ?>
