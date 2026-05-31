<?php
require 'wp-load.php';

$mods = get_option('theme_mods_blocksy', []);

// Define footer structure for 4 columns
$mods['footer_placements'] = [
    'current_section' => 'main',
    'sections' => [
        [
            'id' => 'main',
            'label' => 'Main Footer',
            'items' => [
                [
                    'id' => 'footer_middle_row',
                    'placements' => [
                        [
                            'id' => 'widget_area_1',
                        ],
                        [
                            'id' => 'widget_area_2',
                        ],
                        [
                            'id' => 'widget_area_3',
                        ],
                        [
                            'id' => 'widget_area_4',
                        ]
                    ]
                ],
                [
                    'id' => 'footer_bottom_row',
                    'placements' => [
                        [
                            'id' => 'copyright',
                        ],
                        [
                            'id' => 'socials',
                        ]
                    ]
                ]
            ]
        ]
    ]
];

// Remove 'WordPress Theme by CreativeThemes'
$mods['copyright_text'] = '© {current_year} B Active | Ships nationwide via J&T & Ninja Van | Privacy & Terms';

update_option('theme_mods_blocksy', $mods);
echo 'Footer updated!';
