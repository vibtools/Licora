# Publishing the Licora Python SDK to PyPI

The Python distribution is named `licora`. Applications import it with `import licora_sdk` or `from licora_sdk import LicoraAuth`.

## Release artifacts

Every Licora GitHub tag release builds and validates:

```text
licora-<sdk-version>-py3-none-any.whl
licora-<sdk-version>.tar.gz
Licora-Python-SDK-<sdk-version>.zip
sdk-artifacts.sha256
```

The wheel and source distribution are checked with Twine and the wheel is installed and imported before the GitHub Release is created. The separate SDK ZIP is for developers who prefer a complete downloadable source package.

## Package-name ownership

A missing public project page does not permanently reserve a PyPI name. Ownership of `licora` is established only when an authorized account completes the first accepted upload. Re-check the name immediately before publishing and do not rename the `licora_sdk` import namespace.

## Maintainer prerequisites

1. Create a PyPI maintainer account and enable two-factor authentication.
2. Keep recovery codes in the organization password manager.
3. Download the wheel, source distribution and `sdk-artifacts.sha256` from the same successful GitHub Release.
4. Verify every downloaded SDK artifact against the checksum file.
5. Confirm the SDK version in `pyproject.toml` and `licora_sdk.__version__` is identical and has never been published before.

PyPI versions are immutable. Any code or metadata change requires a new SDK version; never delete and replace a published file.

## Verify the downloaded artifacts

Linux:

```bash
sha256sum -c sdk-artifacts.sha256 --ignore-missing
python -m venv .venv
. .venv/bin/activate
python -m pip install --upgrade pip twine
python -m twine check licora-*.whl licora-*.tar.gz
```

Windows PowerShell:

```powershell
py -m venv .venv
.\.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip twine
python -m twine check licora-*.whl licora-*.tar.gz
```

Compare Windows SHA-256 values with `sdk-artifacts.sha256` using `Get-FileHash -Algorithm SHA256`.

## TestPyPI rehearsal

Use a scoped TestPyPI token through the interactive prompt or a temporary environment variable. Never place a token in source, shell history, workflow files or release assets.

```bash
python -m twine upload --repository testpypi licora-*.whl licora-*.tar.gz
python -m pip install --index-url https://test.pypi.org/simple/ --extra-index-url https://pypi.org/simple/ licora==<sdk-version>
python -c "import licora_sdk; print(licora_sdk.__version__)"
```

## Production PyPI upload

After TestPyPI validation:

```bash
python -m twine upload licora-*.whl licora-*.tar.gz
```

For long-term automation, configure PyPI Trusted Publishing for repository `vibtools/Licora` and a dedicated GitHub environment before adding any publish job. Trusted Publishing should use GitHub OIDC and should not require a stored PyPI API token. The current release workflow intentionally produces upload-ready artifacts without publishing them automatically.

## Post-publish verification

Install into a clean environment from production PyPI:

```bash
python -m venv verify-venv
. verify-venv/bin/activate
python -m pip install "licora==<sdk-version>"
python -c "import licora_sdk; print(licora_sdk.__version__)"
```

Then verify the PyPI project metadata, supported Python versions, project links, README rendering and both wheel/source files. Add the organization maintainers to the PyPI project and keep at least two recovery-capable owners.
