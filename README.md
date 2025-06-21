📦 Usage (Legacy Release)
To get the clean, stable legacy version of Saldi (v0.x) or any specific older version, follow these steps:


git clone https://github.com/DANOSOFT/saldi.git
cd saldi  # or cd saldi/saldi-version [e.g., saldi-0.982] etc.
git fetch --tags
git checkout tags/v0.x-stable -b [your-branch-name]
git clean -fdx   # ⚠️ WARNING: removes all untracked files leftover from master branch 

Note:
The legacy source code is located inside the saldi-version folder (for example, saldi-0.982).
After checkout, navigate into that directory to work with the legacy release files.
