<?php
/** Shipping partners and the store's approved payment-method branding. */
defined('ABSPATH') || exit;

$bactive_cod_enabled = false;
if (function_exists('WC')) {
    try {
        $bactive_manager = WC() ? WC()->payment_gateways() : null;
        $bactive_gateways = $bactive_manager ? $bactive_manager->payment_gateways() : array();
        $bactive_cod_enabled = isset($bactive_gateways['cod'])
            && 'yes' === $bactive_gateways['cod']->enabled;
    } catch (\Throwable $bactive_gateway_error) {
        // Keep optional COD branding hidden when gateway settings cannot be read.
        $bactive_cod_enabled = false;
    }
}
$bactive_payment_marks = array(
    'qrph' => 'QR Ph',
    'maya' => 'Maya',
    'shopeepay' => 'ShopeePay',
    'bpi' => 'BPI Online',
    'unionbank' => 'UnionBank Online',
);
$bactive_theme_url = get_stylesheet_directory_uri();
?>
<style id="bactive-footer-trust-styles">
    .bactive-custom-footer .bactive-trust {
        display: grid;
        gap: 28px;
        margin-bottom: 25px;
        padding-top: 26px;
        border-top: 1px solid var(--bactive-greige, #e5e5e5);
        color: var(--bactive-stone, #686a63);
    }
    .bactive-custom-footer .bactive-trust__shipping {
        display: flex;
        min-width: 0;
        align-items: flex-start;
        justify-content: center;
        flex-wrap: wrap;
        gap: 24px;
    }
    .bactive-custom-footer .bactive-trust__group {
        display: flex;
        min-width: 0;
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }
    .bactive-custom-footer .bactive-trust__label {
        color: var(--bactive-charcoal, #2b2a28);
        font-size: 13px;
        font-weight: 500;
        line-height: 1.4;
    }
    .bactive-custom-footer .bactive-trust__list {
        display: flex;
        max-width: 100%;
        margin: 0;
        padding: 0;
        justify-content: center;
        flex-wrap: wrap;
        gap: 8px;
        list-style: none;
    }
    .bactive-custom-footer .bactive-trust__list--payments {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 108px));
        width: min(100%, 340px);
    }
    .bactive-custom-footer .bactive-trust__list > li {
        min-width: 0;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .bactive-custom-footer .bactive-trust__list > li::before { content: none; }
    .bactive-custom-footer .bactive-trust__badge {
        display: flex;
        width: 108px;
        max-width: 100%;
        height: 44px;
        box-sizing: border-box;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        text-decoration: none;
    }
    .bactive-custom-footer .bactive-trust__badge img {
        display: block;
        width: auto;
        height: auto;
        max-width: 100%;
        max-height: 100%;
        flex-shrink: 0;
        object-fit: contain;
    }
    .bactive-custom-footer .bactive-trust__badge--carrier {
        width: 132px;
        gap: 7px;
        padding: 8px 12px;
        border: 1px solid var(--bactive-greige, #e5e5e5);
        background: #fff;
        color: var(--bactive-charcoal, #2b2a28);
    }
    .bactive-custom-footer .bactive-trust__badge--carrier:hover {
        border-color: var(--bactive-sage-deep, #5e6e54);
        color: var(--bactive-charcoal, #2b2a28);
    }
    .bactive-custom-footer .bactive-trust__badge--carrier:focus-visible {
        outline: 2px solid var(--bactive-sage-deep, #5e6e54);
        outline-offset: 3px;
    }
    .bactive-custom-footer .bactive-trust__badge--jt img {
        max-width: 88px;
        max-height: 24px;
    }
    .bactive-custom-footer .bactive-trust__badge--grab img {
        max-width: 104px;
        max-height: 26px;
    }
    .bactive-custom-footer .bactive-trust__badge--lbc img {
        max-width: 24px;
        max-height: 24px;
    }
    .bactive-custom-footer .bactive-trust__badge--lbc span {
        font-size: 12px;
        font-weight: 600;
        line-height: 1;
        white-space: nowrap;
    }
    .bactive-custom-footer .bactive-trust__processor {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 11px;
        line-height: 1.4;
    }
    .bactive-custom-footer .bactive-trust__processor img {
        display: block;
        width: 92px;
        height: auto;
        object-fit: contain;
    }
    @media (min-width: 1120px) {
        .bactive-custom-footer .bactive-trust {
            grid-template-columns: 1fr 1fr;
            align-items: start;
            column-gap: 64px;
        }
        .bactive-custom-footer .bactive-trust__shipping { justify-content: flex-start; }
        .bactive-custom-footer .bactive-trust__group--shipping { align-items: flex-start; }
        .bactive-custom-footer .bactive-trust__group--payments { align-items: flex-end; }
    }
</style>
<div class="bactive-trust" data-bactive-trust-version="2026-09-05-v4">
    <div class="bactive-trust__shipping">
        <div class="bactive-trust__group bactive-trust__group--shipping" role="group" aria-labelledby="bactive-shipping-label">
            <span class="bactive-trust__label" id="bactive-shipping-label">Ships nationwide via</span>
            <ul class="bactive-trust__list" role="list">
                <li>
                    <a class="bactive-trust__badge bactive-trust__badge--carrier bactive-trust__badge--jt" href="https://www.jtexpress.ph/track-and-trace" target="_blank" rel="noopener noreferrer" aria-label="Track a shipment with J&T Express (opens in a new tab)">
                        <img src="<?php echo esc_url($bactive_theme_url . '/assets/images/couriers/jtexpress.svg'); ?>" width="124" height="39" alt="" loading="lazy" decoding="async" />
                    </a>
                </li>
                <li>
                    <a class="bactive-trust__badge bactive-trust__badge--carrier bactive-trust__badge--lbc" href="https://www.lbcexpress.com/ph/track" target="_blank" rel="noopener noreferrer" aria-label="Track a shipment with LBC Express (opens in a new tab)">
                        <img src="<?php echo esc_url($bactive_theme_url . '/assets/images/couriers/lbc-express.png'); ?>" width="250" height="224" alt="" loading="lazy" decoding="async" />
                        <span>LBC Express</span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="bactive-trust__group bactive-trust__group--shipping" role="group" aria-labelledby="bactive-local-shipping-label">
            <span class="bactive-trust__label" id="bactive-local-shipping-label">Davao City only</span>
            <ul class="bactive-trust__list" role="list">
                <li>
                    <a class="bactive-trust__badge bactive-trust__badge--carrier bactive-trust__badge--grab" href="https://www.grab.com/ph/express/" target="_blank" rel="noopener noreferrer" aria-label="GrabExpress delivery within Davao City only (opens in a new tab)">
                        <img src="<?php echo esc_url($bactive_theme_url . '/assets/images/couriers/grabexpress.png'); ?>" width="2868" height="800" alt="" loading="lazy" decoding="async" />
                    </a>
                </li>
            </ul>
        </div>
    </div>
    <div class="bactive-trust__group bactive-trust__group--payments" role="group" aria-labelledby="bactive-payments-label">
        <span class="bactive-trust__label" id="bactive-payments-label">Payment options</span>
        <ul class="bactive-trust__list bactive-trust__list--payments" role="list">
            <?php // Keep the five user-approved logos visible independently of checkout readiness. ?>
            <?php foreach ($bactive_payment_marks as $bactive_mark => $bactive_label) : ?>
                <li>
                    <span class="bactive-trust__badge">
                        <img src="<?php echo esc_url($bactive_theme_url . '/assets/images/payments/' . $bactive_mark . '.svg'); ?>" width="121" height="49" alt="<?php echo esc_attr($bactive_label); ?>" loading="lazy" decoding="async" />
                    </span>
                </li>
            <?php endforeach; ?>
            <?php if ($bactive_cod_enabled) : ?>
                <li>
                    <span class="bactive-trust__badge">
                        <img src="<?php echo esc_url($bactive_theme_url . '/assets/images/payments/cod.svg'); ?>" width="121" height="49" alt="Cash on Delivery" loading="lazy" decoding="async" />
                    </span>
                </li>
            <?php endif; ?>
        </ul>
        <div class="bactive-trust__processor">
            <span>Online payments via</span>
            <img src="<?php echo esc_url($bactive_theme_url . '/assets/images/payments/paymongo.png'); ?>" width="6122" height="1050" alt="PayMongo" loading="lazy" decoding="async" />
        </div>
    </div>
</div>
