const [major, minor] = process.versions.node.split('.').map(Number);
const supported = major > 22 || (major === 22 && minor >= 12);

if (! supported) {
    console.error(`Good Hours requires Node 22.12 or newer (Node 24 is recommended). Current: ${process.version}.`);
    console.error('Switch Node versions, run npm install, and then retry this command.');
    process.exit(1);
}
