const fs = require('fs');
const path = require('path');
const config = require('../config');

const rootDir = path.join(__dirname, '..');
const outlineIconsDir = path.join(rootDir, 'node_modules', '@tabler', 'icons', 'icons', 'outline');
const filledIconsDir = path.join(rootDir, 'node_modules', '@tabler', 'icons', 'icons', 'filled');
const outputDir = path.join(rootDir, 'dist');
const bladeStubPath = path.join(rootDir, 'stubs', 'icon.blade.stub');

if (!fs.existsSync(outputDir)) {
  fs.mkdirSync(outputDir);
}

const outlineFiles = fs.readdirSync(outlineIconsDir);
const filledFiles = fs.readdirSync(filledIconsDir);

const extractPaths = (svgContent) => {
  const pathMatches = svgContent.match(/<path[^>]*d="([^"]+)"[^>]*>/g);
  if (!pathMatches) return '';
  return pathMatches.map(match => {
    const dMatch = match.match(/d="([^"]+)"/);
    return dMatch ? dMatch[1] : '';
  }).join(' ');
};

let counter = 0;
outlineFiles.forEach(file => {
  const iconName = path.basename(file, '.svg');
  const outlineFilePath = path.join(outlineIconsDir, file);
  const filledFilePath = path.join(filledIconsDir, file);

  const outlineSvgContent = fs.readFileSync(outlineFilePath, 'utf8');
  const filledSvgContent = fs.existsSync(filledFilePath) ? fs.readFileSync(filledFilePath, 'utf8') : outlineSvgContent;

  const outlinePath = extractPaths(outlineSvgContent);
  const filledPath = extractPaths(filledSvgContent);

  const outlineStroke = config.iconSpecificStrokes[iconName] || config.defaultOutlineStroke;

  const bladeTemplate = fs.readFileSync(bladeStubPath, 'utf8')
    .replace('{SVG_OUTLINE_STROKE}', outlineStroke)
    .replace('{SVG_PATH_OUTLINE_24}', outlinePath)
    .replace('{SVG_PATH_SOLID_24}', filledPath)
    .replace('{SVG_PATH_SOLID_20}', filledPath)
    .replace('{SVG_PATH_SOLID_16}', filledPath);

  fs.writeFileSync(path.join(outputDir, `${iconName}.blade.php`), bladeTemplate);
  counter++;
});

console.log(counter + ' icons built successfully!');