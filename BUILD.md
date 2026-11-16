# Build Commands

## Fresh Clone Setup

```bash
npm ci
npm run build
```

## After JS Changes

```bash
npm run build
```

## Release Build

1. `npm run build`

2. `rtp-build --changelog` to copy to clipboard

3. Paste changelog prompt into agent and run

4. Set the release version in:

   - The `Version` plugin header in root file
   - The define or class var in the root file
   - The `Stable tag` in `readme.txt`.

   Leave these version changes uncommitted.

5. `rtp-build`
