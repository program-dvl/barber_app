# Product Hunt demo verification — 2026-11-25

Owner supplied `/Users/dhavalprajapati/Desktop/clipperdesk_demo.mp4`, a native
3024×1534 H.264 recording lasting 207.583 seconds. The user requested macOS's
native recorder; no generated product UI or footage was used.

`render.py` retains the dashboard, calendar, reschedule review, walk-in service
picker, client history, externally received payment recording, staff and reports.
The walk-in picker retains one second and holds its final frame for five seconds
to exclude the following browser autofill popup. Sign-in is omitted entirely.
The reschedule section plays at 2×; other retained action plays at source speed.
Captions are authored explanations, not a speech transcript. Silence is intentional.

Sample frames and adjacent shots were reviewed. The first whole-film review found
several sections began before their intended workspace; exact timestamp frame
checks corrected those ranges. Another review caught personal autofill suggestions;
the final walk-in range excludes them. Reviewed the corrected frame at 25 seconds.
App screens remain authentic, while the closing card uses existing brand artwork.

Final file: `exports/clipperdesk-producthunt-demo.mp4`, 60 seconds, 1920×1080,
30 fps, H.264/YUV420p, approximately 6.1 MB, faststart, no audio stream. FFmpeg
decoded through the end without an error. Static frames were reviewed across the
whole sequence; continuous whole-film playback was not assessed locally. Playback
of the actual hosted video inside Product Hunt's YouTube embed was observed.

Delivery: https://youtu.be/yMZHGiVnx48 — uploaded on the existing Myselfdvl channel,
confirmed Unlisted in YouTube Studio, processing complete and no copyright issues
reported at upload time. YouTube upload terms were explicitly approved by the user.
Product Hunt accepted the link, showed its preview, confirmed Changes saved and
played the video from the first gallery tile. Launch date remains November 30.

CapCut tracking initialization required account connection; the preview helper
started without analytics. No successful telemetry report is claimed. Renderer
installation was declined; editing used the installed FFmpeg and existing fonts.
