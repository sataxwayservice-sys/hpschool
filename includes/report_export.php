<?php
/**
 * Shared helpers for downloadable school reports.
 */

if (!function_exists('reportExportNormalizeSchoolSettings')) {
    function reportExportNormalizeSchoolSettings(array $settings = []): array {
        return [
            'school_name' => trim((string) ($settings['school_name'] ?? APP_NAME)),
            'school_address' => trim((string) ($settings['school_address'] ?? '')),
            'school_phone' => trim((string) ($settings['school_phone'] ?? '')),
            'school_email' => trim((string) ($settings['school_email'] ?? '')),
        ];
    }
}

if (!function_exists('reportExportRenderHeaderBlock')) {
    function reportExportRenderHeaderBlock(array $settings, string $reportTitle, string $generatedAt = '', string $subtitle = '', array $meta = []): string {
        $settings = reportExportNormalizeSchoolSettings($settings);

        ob_start();
        ?>
        <div style="border: 1px solid #dbe3ec; border-left: 6px solid #2563eb; border-radius: 8px; background: #ffffff; padding: 16px 18px; margin-bottom: 14px;">
            <div style="font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #64748b;">
                <?php echo htmlspecialchars($settings['school_name'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div style="font-size: 22px; font-weight: 700; color: #111827; margin-top: 4px;">
                <?php echo htmlspecialchars($reportTitle, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php if ($subtitle !== ''): ?>
                <div style="font-size: 14px; color: #475569; margin-top: 4px;">
                    <?php echo htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <?php if ($settings['school_address'] !== ''): ?>
                <div style="font-size: 13px; color: #334155; margin-top: 8px;">
                    <?php echo htmlspecialchars($settings['school_address'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <?php if ($settings['school_phone'] !== '' || $settings['school_email'] !== ''): ?>
                <div style="font-size: 13px; color: #334155; margin-top: 3px;">
                    <?php if ($settings['school_phone'] !== ''): ?>Phone: <?php echo htmlspecialchars($settings['school_phone'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                    <?php if ($settings['school_phone'] !== '' && $settings['school_email'] !== ''): ?> | <?php endif; ?>
                    <?php if ($settings['school_email'] !== ''): ?>Email: <?php echo htmlspecialchars($settings['school_email'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($generatedAt !== ''): ?>
                <div style="font-size: 12px; color: #64748b; margin-top: 8px;">
                    Generated at: <?php echo htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($meta)): ?>
                <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 6px;">
                    <?php foreach ($meta as $label => $value): ?>
                        <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border: 1px solid #d8e2ee; border-radius: 999px; background: #f8fafc; color: #334155; font-size: 12px;">
                            <strong><?php echo htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8'); ?>:</strong>
                            <span><?php echo htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('reportExportWriteCsvHeaderRows')) {
    function reportExportWriteCsvHeaderRows($handle, array $settings, string $reportTitle, string $generatedAt = '', array $meta = []): void {
        $settings = reportExportNormalizeSchoolSettings($settings);

        $rows = [
            [$settings['school_name']],
        ];

        if ($settings['school_address'] !== '') {
            $rows[] = ['Address', $settings['school_address']];
        }
        if ($settings['school_phone'] !== '') {
            $rows[] = ['Phone', $settings['school_phone']];
        }
        if ($settings['school_email'] !== '') {
            $rows[] = ['Email', $settings['school_email']];
        }

        $rows[] = ['Report', $reportTitle];

        if ($generatedAt !== '') {
            $rows[] = ['Generated At', $generatedAt];
        }

        foreach ($meta as $label => $value) {
            $rows[] = [$label, $value];
        }

        $rows[] = [''];

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
    }
}
