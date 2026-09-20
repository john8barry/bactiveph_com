"""Build a deterministic runtime-only plugin ZIP; never include test fixtures."""
import argparse
import hashlib
from pathlib import Path
import zipfile

parser = argparse.ArgumentParser()
parser.add_argument('output', type=Path, help='New ZIP outside the plugin source tree')
args = parser.parse_args()
root = Path(__file__).resolve().parent
output = args.output.resolve()
if output.is_relative_to(root) or output.exists():
    raise SystemExit('Choose a new output path outside the plugin source tree.')
names = ['bactive-brevo-marketing.php']
for directory, suffixes in [('includes', {'.php'}), ('assets', {'.js', '.css'})]:
    names.extend(str(path.relative_to(root)) for path in sorted((root / directory).iterdir()) if path.suffix in suffixes)
if len(names) < 10:
    raise SystemExit('Runtime source is incomplete.')
for name in names:
    path = root / name
    if path.is_symlink() or not path.is_file() or not path.resolve().is_relative_to(root):
        raise SystemExit('Runtime input must be an ordinary file inside this plugin.')
output.parent.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(output, 'x', compression=zipfile.ZIP_DEFLATED) as archive:
    for name in sorted(names):
        info = zipfile.ZipInfo('bactive-brevo-marketing/' + name, date_time=(2026, 9, 6, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        archive.writestr(info, (root / name).read_bytes())
print('Runtime files:', len(names))
print('SHA256:', hashlib.sha256(output.read_bytes()).hexdigest())
print('Archive:', output)
