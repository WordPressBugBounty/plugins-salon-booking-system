<template>
    <section class="upcoming-promo-carousel" aria-labelledby="upcoming-promos-heading">
        <h2 id="upcoming-promos-heading" class="upcoming-promo-carousel__title">
            {{ getLabel('upcomingPromosSectionTitle') || 'Features & add-ons' }}
        </h2>
        <div class="upcoming-promo-carousel__track" role="list">
            <article
                v-for="(slide, index) in slides"
                :key="slide.href ? `${slide.href}-${index}` : `slide-${index}`"
                class="upcoming-promo-carousel__card"
                :class="{
                    'upcoming-promo-carousel__card--dummy': slide.dummy,
                    'upcoming-promo-carousel__card--license': slide.licensePromo,
                }"
                role="listitem"
            >
                <div class="upcoming-promo-carousel__card-top">
                    <div class="upcoming-promo-carousel__card-icon" aria-hidden="true">
                        <font-awesome-icon :icon="slide.icon" />
                    </div>
                    <span
                        v-if="slide.category"
                        class="upcoming-promo-carousel__category-pill"
                    >{{ slide.category }}</span>
                </div>
                <h3 class="upcoming-promo-carousel__card-title">{{ slide.title }}</h3>
                <p class="upcoming-promo-carousel__card-body">{{ slide.body }}</p>
                <a
                    v-if="slide.href"
                    :href="slide.href"
                    class="upcoming-promo-carousel__card-link"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    {{ slide.cta }}
                </a>
            </article>
        </div>
    </section>
</template>

<script>
/** Heuristic icons for store-driven add-on titles (Font Awesome solid). */
function iconForPromoTitle(title) {
    const t = (title || '').toLowerCase();
    if (t.includes('sms') || t.includes('text message') || t.includes('ovh') || t.includes('twilio')) {
        return 'fa-solid fa-bell';
    }
    if (t.includes('mail') || t.includes('email') || t.includes('communicator') || t.includes('mailchimp')) {
        return 'fa-solid fa-globe';
    }
    if (t.includes('woo') || t.includes('stripe') || t.includes('paypal') || t.includes('payment')) {
        return 'fa-solid fa-store';
    }
    if (t.includes('calendar') || t.includes('google') || t.includes('outlook') || t.includes('apple')) {
        return 'fa-solid fa-calendar-days';
    }
    if (t.includes('price') || t.includes('dynamic')) {
        return 'fa-solid fa-chart-simple';
    }
    if (t.includes('note') || t.includes('soap')) {
        return 'fa-solid fa-pen-to-square';
    }
    if (t.includes('geo') || t.includes('location') || t.includes('multi-shop') || t.includes('branch')) {
        return 'fa-solid fa-globe';
    }
    if (t.includes('package') || t.includes('bundle') || t.includes('membership')) {
        return 'fa-solid fa-medal';
    }
    return 'fa-solid fa-puzzle-piece';
}

/** Placeholder slides when window.slnPWA.dummy_promo_cards is true (dev mock or sln_pwa_dummy_promo_cards filter). */
function getDummyPromoSlides(pricingUrl, ctaLabel) {
    return [
        {
            dummy: true,
            icon: 'fa-solid fa-circle-plus',
            title: 'Sample: new add-on slot',
            body: 'Dummy promo card for layout and carousel testing. Replace with real campaigns in production.',
            href: pricingUrl,
            cta: ctaLabel,
        },
        {
            dummy: true,
            icon: 'fa-solid fa-globe',
            title: 'Sample: booking widget',
            body: 'Placeholder copy. Use this strip to highlight features, integrations, or seasonal offers.',
        },
        {
            dummy: true,
            icon: 'fa-solid fa-images',
            title: 'Sample: gallery & media',
            body: 'Third dummy card so horizontal snap scrolling is easy to verify on a phone.',
            href: pricingUrl,
            cta: ctaLabel,
        },
    ];
}

export default {
    name: 'UpcomingPromoCarousel',
    computed: {
        slides() {
            const pricingUrl = window.slnPWA?.pro_pricing_url || '#';
            const learnMore = this.getLabel('upcomingPromoCtaLabel') || 'Learn more';
            const licenseLast = this.buildLicenseUpgradeSlide(pricingUrl, learnMore);

            const featured = Array.isArray(window.slnPWA?.featured_addon_promos)
                ? window.slnPWA.featured_addon_promos
                : [];

            let rest = [];
            if (featured.length) {
                rest = featured.map((item) => ({
                    icon: iconForPromoTitle(item.title),
                    title: item.title || '',
                    body: item.body || '',
                    href: item.href || '',
                    cta: item.cta || learnMore,
                    category: typeof item.category === 'string' ? item.category.trim() : '',
                }));
            } else {
                rest = this.buildLegacyPromoSlides(pricingUrl, learnMore);
            }

            if (licenseLast) {
                return [...rest, licenseLast];
            }
            return rest;
        },
    },
    methods: {
        buildLicenseUpgradeSlide(pricingUrl, fallbackCta) {
            const raw = window.slnPWA?.license_upgrade_promo;
            if (!raw || !raw.kind) {
                return null;
            }
            if (raw.kind === 'free_pro') {
                return {
                    licensePromo: true,
                    icon: 'fa-solid fa-unlock',
                    title: this.getLabel('pwaLicensePromoFreeProTitle') || '',
                    body: this.getLabel('pwaLicensePromoFreeProBody') || '',
                    href: raw.href || pricingUrl,
                    cta: this.getLabel('pwaLicensePromoFreeProCta') || fallbackCta,
                };
            }
            if (raw.kind === 'basic_business') {
                return {
                    licensePromo: true,
                    icon: 'fa-solid fa-rocket',
                    title: this.getLabel('pwaLicensePromoBasicBusinessTitle') || '',
                    body: this.getLabel('pwaLicensePromoBasicBusinessBody') || '',
                    href: raw.href || pricingUrl,
                    cta: this.getLabel('pwaLicensePromoBasicBusinessCta') || fallbackCta,
                };
            }
            return null;
        },
        buildLegacyPromoSlides(pricingUrl, cta) {
            const base = [
                {
                    icon: 'fa-solid fa-chart-simple',
                    title: this.getLabel('upcomingPromo1Title') || 'Dynamic pricing',
                    body: this.getLabel('upcomingPromo1Body') || '',
                    href: pricingUrl,
                    cta,
                },
                {
                    icon: 'fa-solid fa-bell',
                    title: this.getLabel('upcomingPromo2Title') || 'SMS notifications',
                    body: this.getLabel('upcomingPromo2Body') || '',
                },
                {
                    icon: 'fa-solid fa-medal',
                    title: this.getLabel('upcomingPromo3Title') || 'Full mobile workflow',
                    body: this.getLabel('upcomingPromo3Body') || '',
                    href: pricingUrl,
                    cta,
                },
            ];
            if (window.slnPWA?.dummy_promo_cards !== false) {
                return [...base, ...getDummyPromoSlides(pricingUrl, cta)];
            }
            return base;
        },
    },
};
</script>

<style scoped>
.upcoming-promo-carousel {
    --promo-brand: #2171b1;
    --promo-surface: #eeeeef;
    --promo-text: #2d2d2d;
    --promo-border: rgba(45, 45, 45, 0.12);

    margin-top: 24px;
    padding: 18px 0 20px;
    border-top: 1px solid var(--promo-border);
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}

.upcoming-promo-carousel__title {
    font-size: 15px;
    font-weight: 700;
    color: var(--promo-brand);
    margin: 0 0 14px;
    letter-spacing: 0.01em;
}

.upcoming-promo-carousel__track {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    overflow-y: hidden;
    padding: 4px 4px 16px 2px;
    margin: 0 -4px 0 -2px;
    scroll-snap-type: x mandatory;
    scroll-padding-left: 2px;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    overscroll-behavior-x: contain;
}

.upcoming-promo-carousel__track::-webkit-scrollbar {
    height: 4px;
}

.upcoming-promo-carousel__track::-webkit-scrollbar-thumb {
    background: var(--promo-brand);
    border-radius: 4px;
}

.upcoming-promo-carousel__card {
    flex: 0 0 min(82vw, 272px);
    max-width: 272px;
    scroll-snap-align: start;
    background: var(--promo-surface);
    border: 1px solid var(--promo-border);
    border-radius: var(--radius-lg, 16px);
    padding: 14px 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-height: 11rem;
}

/* License / upgrade: same flat grey surface, blue outline only */
.upcoming-promo-carousel__card--license {
    background: var(--promo-surface);
    border: 2px solid var(--promo-brand);
}

.upcoming-promo-carousel__card--license.upcoming-promo-carousel__card--dummy {
    background: var(--promo-surface);
    border: 2px dashed var(--promo-brand);
}

.upcoming-promo-carousel__card--dummy {
    border-style: dashed;
    border-color: var(--promo-border);
    background: var(--promo-surface);
}

.upcoming-promo-carousel__card--dummy .upcoming-promo-carousel__card-icon {
    background: transparent;
    color: var(--promo-brand);
}

.upcoming-promo-carousel__card-top {
    display: flex;
    flex-direction: row;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}

.upcoming-promo-carousel__card-icon {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: transparent;
    color: var(--promo-brand);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.upcoming-promo-carousel__category-pill {
    flex-shrink: 1;
    min-width: 0;
    align-self: center;
    max-width: 100%;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.2;
    letter-spacing: 0.02em;
    color: var(--promo-text);
    background: #ffffff;
    border: 1px solid var(--promo-brand);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.upcoming-promo-carousel__card-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--promo-brand);
    margin: 0;
    line-height: 1.25;
}

.upcoming-promo-carousel__card-body {
    font-size: 13px;
    line-height: 1.45;
    color: var(--promo-text);
    margin: 0;
    flex: 1;
}

.upcoming-promo-carousel__card-link {
    font-size: 13px;
    font-weight: 600;
    color: var(--promo-brand);
    text-decoration: none;
    margin-top: auto;
    padding-top: 4px;
}

.upcoming-promo-carousel__card-link:hover,
.upcoming-promo-carousel__card-link:focus {
    color: var(--promo-brand);
    text-decoration: underline;
    text-underline-offset: 2px;
}
</style>
