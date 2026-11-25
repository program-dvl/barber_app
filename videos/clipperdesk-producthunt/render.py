"""Cut the owner-supplied native recording; never include its sign-in interval."""
from pathlib import Path
import subprocess
import json

ROOT = Path(__file__).resolve().parents[2]
PROJECT = Path(__file__).resolve().parent
SOURCE = Path('/Users/dhavalprajapati/Desktop/clipperdesk_demo.mp4')
FONT = ROOT / 'public/fonts/clipperdesk/manrope-600.ttf'
SHOTS = [
    (10, 16, 1, 'Start with your working day'),
    (26, 34, 1, 'See appointments alongside staff availability'),
    (35, 53, 2, 'Review a new time before saving a change'),
    (83, 89, 1, 'Welcome walk-ins with saved client details'),
    (98, 105, 1, 'Keep client history ready for the next visit'),
    (110, 117, 1, 'Record payments received elsewhere — no card is charged'),
    (124, 129, 1, 'Coordinate your team and working hours'),
    (154, 161, 1, 'Review sales and understand the day'),
]

def run(args):
    subprocess.run(['ffmpeg', '-hide_banner', '-loglevel', 'error', '-y', *args], check=True)

def render_shot(index, shot):
    start, end, speed, caption = shot
    label = PROJECT / 'review' / f'caption-{index}.txt'
    label.write_text(caption)
    filters = (
        f'setpts=(PTS-STARTPTS)/{speed},fps=30,scale=1920:974,'
        'pad=1920:1080:0:0:color=0x111a35,'
        f'drawtext=fontfile={FONT}:textfile={label}:fontsize=36:'
        'fontcolor=white:x=(w-tw)/2:y=991,'
        f'drawtext=fontfile={FONT}:text=ClipperDesk · Demo account · Sample data:'
        'fontsize=19:fontcolor=0xb5c6df:x=(w-tw)/2:y=1046'
    )
    out = PROJECT / 'exports' / f'shot-{index:02}.mp4'
    run(['-ss', str(start), '-t', str(end-start), '-i', str(SOURCE), '-an',
         '-vf', filters, '-c:v', 'libx264', '-preset', 'fast', '-crf', '19',
         '-pix_fmt', 'yuv420p', '-movflags', '+faststart', str(out)])
    return out

if __name__ == '__main__':
    import sys
    sample = '--sample' in sys.argv
    outputs = [render_shot(i, shot) for i, shot in enumerate(SHOTS[:2] if sample else SHOTS)]
    if not sample:
        endcard = PROJECT / 'exports/endcard.mp4'
        run(['-f', 'lavfi', '-i', 'color=c=0xf4f6fa:s=1920x1080:r=30',
             '-loop', '1', '-i', str(ROOT / 'public/images/brand/clipperdesk-cd-square-transparent.png'),
             '-filter_complex', f'[1:v]scale=180:180[logo];[0:v][logo]overlay=870:200,'
             f'drawtext=fontfile={FONT}:text=ClipperDesk:fontsize=76:fontcolor=0x111a35:x=(w-tw)/2:y=430,'
             f'drawtext=fontfile={FONT}:text=Your whole day. Beautifully run.:fontsize=48:fontcolor=0x111a35:x=(w-tw)/2:y=550,'
             f'drawtext=fontfile={FONT}:text=Try a sample day at clipperdesk.com:fontsize=38:fontcolor=0x4036bd:x=(w-tw)/2:y=720',
             '-t', '5', '-an', '-c:v', 'libx264', '-preset', 'fast', '-crf', '19', '-pix_fmt', 'yuv420p', str(endcard)])
        outputs.append(endcard)
    concat = PROJECT / 'review' / ('sample-concat.txt' if sample else 'concat.txt')
    concat.write_text(''.join(f"file '{p}'\n" for p in outputs))
    final = PROJECT / ('review/sample.mp4' if sample else 'exports/clipperdesk-producthunt-demo.mp4')
    run(['-f', 'concat', '-safe', '0', '-i', str(concat), '-c', 'copy', '-movflags', '+faststart', str(final)])
    (PROJECT / 'project.json').write_text(json.dumps({'source':str(SOURCE), 'sample_data':True,
        'audio':'intentionally silent; caption-led product walkthrough', 'shots':SHOTS,
        'output':str(final)}, indent=2))
    print(final)
