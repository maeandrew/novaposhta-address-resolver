# Releasing

[English](RELEASING.md) · [Українська](uk/RELEASING.md)

The package uses Semantic Versioning and tags releases in Git with a leading
`v`, for example `v0.1.0`.

## Version policy

- `PATCH` fixes a bug without changing the public API.
- `MINOR` adds backwards-compatible functionality.
- `MAJOR` changes or removes a public contract.

The milestone versions are `v0.1.0` for M0/M1, `v0.2.0` for M2, and `v0.3.0`
for M3. The first stable API will be tagged `v1.0.0` after a public
compatibility review.

## Release steps

1. Update `CHANGELOG.md` and move the completed entries from `Unreleased` to a
   dated version section.
2. Run the complete offline quality suite:

   ```bash
   ddev composer quality
   ```

3. Commit the release change and create an annotated tag:

   ```bash
   git add CHANGELOG.md
   git commit -m "Prepare v0.3.0 release"
   git tag -a v0.3.0 -m "Release v0.3.0"
   git push origin main
   git push origin v0.3.0
   ```

4. GitHub Actions validates the tag, reruns the matrix on PHP 8.2 and 8.3, and
   creates the GitHub Release when all checks pass.

Composer resolves a GitHub VCS package from these tags. Once the package is
published to Packagist, consumers can use:

```bash
composer require maeandrew/novaposhta-address-resolver:^0.3
```

The Laravel bridge is released from its own repository as
`maeandrew/novaposhta-address-resolver-laravel`. Its tag and Packagist package
version follow the root release, while its Composer dependency remains the
framework-free core.

Before Packagist publication, a consumer can register the GitHub repository as
a Composer VCS repository and require the same tagged constraint.
