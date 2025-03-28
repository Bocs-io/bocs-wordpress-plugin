Help me organize my Git workflow by analyzing the changes in my project and suggesting logical groups of files to stage and commit together. For each group of files:

1. Analyze the files that have been modified in my working directory
2. Group related files together based on:
   - Files in the same directory/module
   - Files that contribute to the same feature or fix
   - Files with similar types of changes (e.g., refactoring, documentation, feature implementation)

3. For each identified group, provide:
   - A list of files to add with the appropriate `git add` command
   - A conventional commit message following the format: `type(scope): description`
   - Where:
     - `type` = feat, fix, docs, style, refactor, test, chore, etc.
     - `scope` = the module or component affected
     - `description` = a concise description of the changes

4. Present a complete multi-step process that I can follow to stage and commit all changes in a logical, organized manner.

Example output:

```
Group 1: API Authentication Updates
Files:
- src/auth/models.py
- src/auth/controllers.py
- tests/auth/test_authentication.py

Commands:
git add src/auth/models.py src/auth/controllers.py tests/auth/test_authentication.py
git commit -m "feat(auth): implement token refresh mechanism"

Group 2: Documentation Updates
Files:
- README.md
- docs/api.md

Commands:
git add README.md docs/api.md
git commit -m "docs(api): update authentication documentation"

...and so on for all detected changes
```

Please analyze my current working directory and provide me with this organized approach to staging and committing my changes.