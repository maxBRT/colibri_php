// @ts-check

// This runs in Node.js - Don't use client-side code here (browser APIs, JSX...)

import fs from 'fs';
import path from 'path';
import {fileURLToPath} from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/** @type {() => import('@docusaurus/plugin-content-docs').SidebarItem[]} */
function loadApiSidebar() {
  const sidebarPath = path.join(__dirname, 'docs/api/sidebar.ts');
  if (!fs.existsSync(sidebarPath)) {
    console.warn('API sidebar is missing. Run `npm run gen-api` to generate it.');
    return [];
  }

  const raw = fs.readFileSync(sidebarPath, 'utf8');
  const sanitized = raw
    .replace(/import[^;]+;\s*/g, '')
    .replace(/: SidebarsConfig/g, '');
  const match = sanitized.match(/const\s+sidebar\s*=\s*(\{[\s\S]*?\});/);
  if (!match) {
    console.warn('Unable to parse generated API sidebar.');
    return [];
  }

  try {
    const expression = match[1];
    const factory = new Function(`return (${expression});`);
    const sidebarObject = factory();
    return sidebarObject.apisidebar ?? [];
  } catch (error) {
    console.warn('Failed to evaluate generated API sidebar.', error);
    return [];
  }
}

const apiItems = loadApiSidebar();

/**
 * @type {import('@docusaurus/plugin-content-docs').SidebarsConfig}
 */
const sidebars = {
  tutorialSidebar: [
    'Introduction',
    {
      type: 'category',
      label: 'API',
      collapsed: false,
      items: apiItems,
    },
    {
      type: 'category',
      label: 'Contribute',
      collapsed: false,
      items: [
        'contribute/contribute-code',
      ],
    },
  ],
};

export default sidebars;
