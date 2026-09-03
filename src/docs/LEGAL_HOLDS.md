# Legal holds

Kairox is responsible for deciding when to issue or release a hold and selecting the controlled reason code. Likeslocale provides technical enforcement and auditability; the system does not infer legal reasons.

Holds have an explicit `ACTIVE → RELEASED` lifecycle and preserve released history. Free-form narratives are not stored. Supported scopes are:

- subject: protects all categories/resources for the subject;
- category: protects that subject's entire named category;
- resource: protects one stable supported resource belonging to the subject.

Broader scopes override narrower eligibility. Ambiguous resource ownership fails closed; operators should use a broader scope when applicability cannot be established.

Hold issuance and every destructive item execution acquire the same `retention_subject_locks` database row. After obtaining that fence, the system uses locking/current reads for resource scope and final hold applicability; it never treats an older MySQL `REPEATABLE READ` snapshot as current authority. The fence remains owned through destructive database commit. A hold either commits first and is seen by execution, or execution commits first and hold issuance proceeds afterward—there is no unlocked check-to-delete interval. Hold creation/release uses current administrator authentication, valid security-version session, MFA, explicit direct permission, and recent password confirmation.

Ordinary applicant-document deletion/replacement and profile-resume replacement/removal use the same subject fence. Existing profile, application, application-file, and applicant-document artifacts are protected by applicable subject/category/resource holds, and every after-commit cleanup carries the subject/category/resource identity captured before logical deletion. The cleanup job reacquires the subject fence and current-reads holds even when the source row is already gone. Incomplete context retains the file. A new profile upload with no prior artifact remains non-destructive and is not blocked merely by the existence of a hold.

Legacy public-to-private migration is preservation-first. An active applicable hold may permit a verified private copy, but the command keeps the legacy reference and old location. It does not remove the held location until a later run after release.

The persistent subject-fence row uses a restrictive user foreign key. Permanent account deletion therefore remains safely blocked when Pass 3 fence or evidence rows still reference that user; operational cleanup of those records requires a separately approved lifecycle decision and is not inferred here.

Releasing a hold allows future technical eligibility evaluation; it does not authorize an existing or future plan and does not express a legal conclusion.
