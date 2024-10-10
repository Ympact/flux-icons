const fs = require('fs');
const path = require('path');
const buildScript = require('./build');

test('build script generates blade files', () => {
  const outputDir = path.join(__dirname, 'dist');

  // Clean up the output directory before running the build script
  if (fs.existsSync(outputDir)) {
    fs.rmdirSync(outputDir, { recursive: true });
  }

  // Run the build script
  buildScript();

  // Check if the output directory exists
  expect(fs.existsSync(outputDir)).toBe(true);

  // Read the files in the output directory
  const files = fs.readdirSync(outputDir);

  // Log the files for debugging purposes
  console.log('Generated files:', files);

  // Check if at least one file is generated
  expect(files.length).toBeGreaterThan(0);
});