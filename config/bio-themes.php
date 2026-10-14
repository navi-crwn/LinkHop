<?php

// Theme presets shared by the editor, its live preview and the public bio page (M1).
// A preset's colours always win over the page's own colours when the page uses it.
return [
    // Choices in the editor that only fill in the page's own colours; the public page does not enforce them.
    'starters' => [
        'default' => ['name' => 'Default', 'background' => '#f8fafc', 'text' => '#1e293b', 'bio' => '#64748b', 'link_bg' => '#6366f1', 'link_text' => '#ffffff'],
        'minimal' => ['name' => 'Minimal', 'background' => '#ffffff', 'text' => '#000000', 'bio' => '#6b7280', 'link_bg' => '#000000', 'link_text' => '#ffffff'],
    ],

    'presets' => [
        'light' => ['name' => 'Light', 'background' => '#ffffff', 'text' => '#1e293b', 'bio' => '#64748b', 'link_bg' => '#f1f5f9', 'link_text' => '#1e293b'],
        'dark' => ['name' => 'Dark', 'background' => 'linear-gradient(135deg, #1e293b 0%, #0f172a 100%)', 'text' => '#f1f5f9', 'bio' => 'rgba(241,245,249,0.8)', 'link_bg' => 'rgba(51, 65, 85, 0.9)', 'link_text' => '#f1f5f9'],
        'modern' => ['name' => 'Modern', 'background' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)', 'text' => '#ffffff', 'bio' => 'rgba(255,255,255,0.8)', 'link_bg' => 'rgba(255, 255, 255, 0.2)', 'link_text' => '#ffffff'],
        'classic' => ['name' => 'Classic', 'background' => 'linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%)', 'text' => '#1e293b', 'bio' => '#64748b', 'link_bg' => '#ffffff', 'link_text' => '#1e293b'],
        'minimal-light' => ['name' => 'Minimal Light', 'background' => '#fafafa', 'text' => '#171717', 'bio' => '#525252', 'link_bg' => '#ffffff', 'link_text' => '#171717'],
        'minimal-dark' => ['name' => 'Minimal Dark', 'background' => '#171717', 'text' => '#fafafa', 'bio' => '#a3a3a3', 'link_bg' => '#262626', 'link_text' => '#fafafa'],
        'ocean' => ['name' => 'Ocean', 'background' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)', 'text' => '#ffffff', 'bio' => 'rgba(255,255,255,0.8)', 'link_bg' => 'rgba(255, 255, 255, 0.2)', 'link_text' => '#ffffff'],
        'sunset' => ['name' => 'Sunset', 'background' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)', 'text' => '#ffffff', 'bio' => 'rgba(255,255,255,0.9)', 'link_bg' => 'rgba(255, 255, 255, 0.25)', 'link_text' => '#ffffff'],
        'forest' => ['name' => 'Forest', 'background' => 'linear-gradient(135deg, #134e5e 0%, #71b280 100%)', 'text' => '#ffffff', 'bio' => 'rgba(255,255,255,0.85)', 'link_bg' => 'rgba(255, 255, 255, 0.2)', 'link_text' => '#ffffff'],
        'galaxy' => ['name' => 'Galaxy', 'background' => 'linear-gradient(135deg, #0c0a3e 0%, #2d1b69 50%, #7b2cbf 100%)', 'text' => '#ffffff', 'bio' => '#e0aaff', 'link_bg' => 'rgba(255,255,255,0.15)', 'link_text' => '#ffffff'],
        'neon' => ['name' => 'Neon', 'background' => '#0a0a0a', 'text' => '#39ff14', 'bio' => '#00ff88', 'link_bg' => 'rgba(57, 255, 20, 0.15)', 'link_text' => '#39ff14', 'link_border' => '#39ff14'],
        'pastel' => ['name' => 'Pastel', 'background' => 'linear-gradient(180deg, #ffecd2 0%, #fcb69f 100%)', 'text' => '#5c4742', 'bio' => '#8b7355', 'link_bg' => '#ffffff', 'link_text' => '#5c4742'],
        'cyberpunk' => ['name' => 'Cyberpunk', 'background' => 'linear-gradient(135deg, #1a1a2e 0%, #16213e 100%)', 'text' => '#00fff5', 'bio' => '#ff006e', 'link_bg' => 'rgba(0,255,245,0.1)', 'link_text' => '#00fff5'],
        'aurora' => ['name' => 'Aurora', 'background' => 'linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%)', 'text' => '#b8b8d1', 'bio' => '#7f7f9a', 'link_bg' => 'rgba(88,86,214,0.3)', 'link_text' => '#ffffff'],
        'cherry' => ['name' => 'Cherry Blossom', 'background' => 'linear-gradient(180deg, #ffc0cb 0%, #ffb6c1 50%, #ff69b4 100%)', 'text' => '#4a1942', 'bio' => '#722f37', 'link_bg' => '#ffffff', 'link_text' => '#4a1942'],
        'midnight' => ['name' => 'Midnight', 'background' => 'linear-gradient(180deg, #0f0f23 0%, #1a1a3e 100%)', 'text' => '#e8e8ff', 'bio' => '#8888aa', 'link_bg' => 'rgba(99,102,241,0.2)', 'link_text' => '#a5b4fc'],
        'retro' => ['name' => 'Retro', 'background' => '#f4e4ba', 'text' => '#2d1b00', 'bio' => '#5c4033', 'link_bg' => '#e07a5f', 'link_text' => '#ffffff'],
        'ice' => ['name' => 'Ice', 'background' => 'linear-gradient(180deg, #e0f7fa 0%, #b2ebf2 50%, #80deea 100%)', 'text' => '#006064', 'bio' => '#00838f', 'link_bg' => '#ffffff', 'link_text' => '#00838f'],
        'lavender' => ['name' => 'Lavender Dreams', 'background' => 'linear-gradient(135deg, #e6e6fa 0%, #d8bfd8 50%, #dda0dd 100%)', 'text' => '#4b0082', 'bio' => '#663399', 'link_bg' => '#ffffff', 'link_text' => '#4b0082'],
        'matrix' => ['name' => 'Matrix', 'background' => '#000000', 'text' => '#00ff00', 'bio' => '#00cc00', 'link_bg' => 'rgba(0,255,0,0.1)', 'link_text' => '#00ff00'],
    ],

    // Presets offered in the editor, in picker order. The others remain for pages that already use them.
    'picker' => ['default', 'dark', 'minimal', 'ocean', 'sunset', 'forest', 'galaxy', 'neon', 'pastel', 'cyberpunk', 'aurora', 'cherry', 'midnight', 'retro', 'ice', 'lavender', 'matrix'],

    'glow' => [
        'default' => 'rgba(99, 102, 241, 0.4)',
        'light' => 'rgba(99, 102, 241, 0.4)',
        'dark' => 'rgba(96, 165, 250, 0.5)',
        'modern' => 'rgba(139, 92, 246, 0.5)',
        'classic' => 'rgba(71, 85, 105, 0.4)',
        'minimal-light' => 'rgba(23, 23, 23, 0.3)',
        'minimal-dark' => 'rgba(250, 250, 250, 0.3)',
        'ocean' => 'rgba(118, 75, 162, 0.5)',
        'sunset' => 'rgba(245, 87, 108, 0.5)',
        'forest' => 'rgba(113, 178, 128, 0.5)',
        'galaxy' => 'rgba(123, 44, 191, 0.5)',
        'neon' => 'rgba(57, 255, 20, 0.5)',
        'pastel' => 'rgba(252, 182, 159, 0.5)',
        'cyberpunk' => 'rgba(0, 255, 245, 0.5)',
        'aurora' => 'rgba(88, 86, 214, 0.5)',
        'cherry' => 'rgba(255, 105, 180, 0.5)',
        'midnight' => 'rgba(165, 180, 252, 0.5)',
        'retro' => 'rgba(224, 122, 95, 0.5)',
        'ice' => 'rgba(0, 131, 143, 0.5)',
        'lavender' => 'rgba(75, 0, 130, 0.4)',
        'matrix' => 'rgba(0, 255, 0, 0.5)',
    ],

    'hue' => [
        'default' => '30deg',
        'neon' => '60deg',
        'cyberpunk' => '-40deg',
        'matrix' => '120deg',
        'sunset' => '-30deg',
        'ocean' => '40deg',
        'galaxy' => '45deg',
        'aurora' => '50deg',
        'cherry' => '-20deg',
        'ice' => '20deg',
        'retro' => '-25deg',
    ],
];
