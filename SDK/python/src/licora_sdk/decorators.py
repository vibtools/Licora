"""Small framework-neutral authorization helpers."""
from __future__ import annotations

from functools import wraps
from typing import Callable, ParamSpec, TypeVar

from .errors import LicenseRequiredError
from .manager import LicoraAuth


P = ParamSpec("P")
R = TypeVar("R")


def require_license(auth: LicoraAuth) -> Callable[[Callable[P, R]], Callable[P, R]]:
    """Guard a function with the current locally verified access token."""

    def decorate(function: Callable[P, R]) -> Callable[P, R]:
        @wraps(function)
        def guarded(*args: P.args, **kwargs: P.kwargs) -> R:
            if not auth.is_licensed():
                snapshot = auth.snapshot()
                raise LicenseRequiredError(
                    snapshot.code or "LICENSE_REQUIRED",
                    snapshot.message or "An active Licora license is required.",
                )
            return function(*args, **kwargs)

        return guarded

    return decorate
