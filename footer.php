<?php

if (!function_exists('footer_defaults_fallback')) {
    function footer_defaults_fallback(): array
    {
        return [
            'brand_name' => 'BV-BrightVision',
            'copyright_owner' => 'BV-BrightVision',
            'rights_text' => 'All rights reserved.',
            'developer_name' => 'ONYX',
            'developer_url' => 'https://onyxrns.com',
            'collaboration_text' => 'Collaborated with apexinventives',
            'link_1_label' => 'Privacy',
            'link_1_url' => '#',
            'link_2_label' => 'Terms',
            'link_2_url' => '#',
            'link_3_label' => 'Contact',
            'link_3_url' => '#'
        ];
    }
}

if (!function_exists('footer_normalize_url')) {
    function footer_normalize_url(string $url, string $fallback = '#'): string
    {
        $url = trim($url);
        if ($url === '') {
            return $fallback;
        }
        if ($url === '#') {
            return '#';
        }

        if (preg_match('~^(https?://|mailto:|tel:|/|#)~i', $url)) {
            return $url;
        }

        $candidate = 'https://' . ltrim($url, '/');
        if (!filter_var($candidate, FILTER_VALIDATE_URL)) {
            return $fallback;
        }
        return $candidate;
    }
}

if (!function_exists('footer_is_external_url')) {
    function footer_is_external_url(string $url): bool
    {
        return preg_match('~^https?://~i', $url) === 1;
    }
}

if (!function_exists('normalize_footer_settings')) {
    function normalize_footer_settings(array $settings = []): array
    {
        $defaults = function_exists('default_footer_settings')
            ? default_footer_settings()
            : footer_defaults_fallback();

        $merged = array_merge($defaults, $settings);

        $brandName = trim((string) ($merged['brand_name'] ?? ''));
        $copyrightOwner = trim((string) ($merged['copyright_owner'] ?? ''));
        $rightsText = trim((string) ($merged['rights_text'] ?? ''));

        $developerName = trim((string) ($merged['developer_name'] ?? ($merged['text'] ?? '')));
        $developerName = preg_replace('/^designed\s*(and|&)\s*developed\s*by\s*/i', '', $developerName);
        $developerName = trim((string) $developerName);
        $developerUrl = footer_normalize_url((string) ($merged['developer_url'] ?? ($merged['url'] ?? '')), $defaults['developer_url']);

        $collaborationText = trim((string) ($merged['collaboration_text'] ?? ''));

        $brandName = $brandName !== '' ? $brandName : $defaults['brand_name'];
        $copyrightOwner = $copyrightOwner !== '' ? $copyrightOwner : $defaults['copyright_owner'];
        $rightsText = $rightsText !== '' ? $rightsText : $defaults['rights_text'];
        $developerName = $developerName !== '' ? $developerName : $defaults['developer_name'];
        $collaborationText = $collaborationText !== '' ? $collaborationText : $defaults['collaboration_text'];

        $links = [];
        for ($i = 1; $i <= 3; $i++) {
            $labelKey = 'link_' . $i . '_label';
            $urlKey = 'link_' . $i . '_url';
            $label = trim((string) ($merged[$labelKey] ?? ''));
            $url = footer_normalize_url((string) ($merged[$urlKey] ?? ''), '#');
            if ($label === '') {
                continue;
            }
            $links[] = [
                'label' => $label,
                'url' => $url
            ];
        }

        return [
            'brand_name' => $brandName,
            'copyright_owner' => $copyrightOwner,
            'rights_text' => $rightsText,
            'developer_name' => $developerName,
            'developer_url' => $developerUrl,
            'collaboration_text' => $collaborationText,
            'links' => $links
        ];
    }
}

if (!function_exists('render_portal_footer')) {
    function render_portal_footer(array $settings = [], string $extraClass = 'no-print'): void
    {
        $s = normalize_footer_settings($settings);
        $footerClass = trim('portal-footer ' . $extraClass);
        ?>
        <footer class="<?= htmlspecialchars($footerClass) ?>">
            <div class="footer-brand">
                <i class="fas fa-graduation-cap"></i>
                <span><?= htmlspecialchars($s['brand_name']) ?></span>
            </div>
            <p class="footer-copy">
                &copy; <?= date('Y') ?> <?= htmlspecialchars($s['copyright_owner']) ?>.
                <?= htmlspecialchars($s['rights_text']) ?>
                Designed &amp; Developed by
                <?php
                $devIsExternal = footer_is_external_url($s['developer_url']);
                ?>
                <a
                    class="footer-inline-link"
                    href="<?= htmlspecialchars($s['developer_url']) ?>"
                    <?= $devIsExternal ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                >
                    <?= htmlspecialchars($s['developer_name']) ?>
                </a>
            </p>
            <?php if (trim((string) $s['collaboration_text']) !== ''): ?>
                <p class="footer-collab"><?= htmlspecialchars($s['collaboration_text']) ?></p>
            <?php endif; ?>
            <?php if (!empty($s['links'])): ?>
                <div class="footer-links">
                    <?php foreach ($s['links'] as $index => $link): ?>
                        <?php
                        $isExternal = footer_is_external_url($link['url']);
                        if ($index > 0) {
                            echo '<span>&bull;</span>';
                        }
                        ?>
                        <a
                            href="<?= htmlspecialchars($link['url']) ?>"
                            <?= $isExternal ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                        >
                            <?= htmlspecialchars($link['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </footer>
        <?php
    }
}
