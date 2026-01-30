# Sync Process Flowchart

This diagram shows the complete sync process with file references.

```mermaid
%%{init: {'theme': 'neutral'}}%%
flowchart TD
    A[CLI Invoked<br/><code>bin/verteller.php</code>] --> B{Parse Arguments}

    B -->|&nbsp;help flag&nbsp;| C[Show Help Text<br/>Exit 0]
    B -->|&nbsp;Options parsed&nbsp;| D[Create SyncRunner<br/><code>SyncRunner.php</code>]

    D --> E[SyncRunner::run<br/>dryRun, validate, syncHashes]

    E --> F[Find Story Files<br/><code>findStoryFiles</code><br/>Scan storyDir for .story files]

    F --> G{Story Files Found?}
    G -->|&nbsp;No&nbsp;| H[Empty Result<br/>Exit 0]
    G -->|&nbsp;Yes&nbsp;| I[Loop: Each Story File]

    I --> J[Resolve Test Directory<br/><code>NamespaceResolverInterface</code>]

    J --> K[Read Story File Contents]

    K --> L[StoryParser::parse<br/><code>StoryParser.php</code>]

    L --> M{Scenarios Found?}
    M -->|&nbsp;No&nbsp;| N[Add Error<br/>No scenarios in file]
    N --> NEXT{More Files?}

    M -->|&nbsp;Yes&nbsp;| O[Determine Test File Path<br/>testsDir/StoryNameTest.php]

    O --> P{Test File Exists?}

    P -->|&nbsp;Yes&nbsp;| R[MethodExtractor::extract<br/><code>MethodExtractor.php</code><br/>Find existing CoversStory methods]
    P -->|&nbsp;No&nbsp;| R2[Use Empty Methods Array]

    R --> S{syncHashes Mode?}
    R2 --> S

    S -->|&nbsp;Yes&nbsp;| T[Find Hash Mismatches<br/>Compare scenario.bodyHash vs attribute hash]

    T --> U{Mismatches Found?}
    U -->|&nbsp;No&nbsp;| NEXT
    U -->|&nbsp;Yes&nbsp;| V[Record HashSynced Event]

    V --> W{Dry Run?}
    W -->|&nbsp;Yes&nbsp;| NEXT
    W -->|&nbsp;No&nbsp;| X[TestFileWriter::syncHashes<br/>Update hash values in file]
    X --> NEXT

    S -->|&nbsp;No&nbsp;| Y[Check Orphan Methods<br/>Warn if method exists but scenario removed]

    Y --> Z[For Each Scenario]

    Z --> AA{Method Exists?}
    AA -->|&nbsp;No&nbsp;| AB[Mark as New Method]
    AA -->|&nbsp;Yes&nbsp;| AC[Check Hash Mismatch<br/>Collect if changed]

    AC --> AE{Is Outline?}
    AE -->|&nbsp;Yes&nbsp;| AF{Provider Changed?}
    AF -->|&nbsp;Yes&nbsp;| AG[Mark Provider for Update]
    AF -->|&nbsp;No&nbsp;| AH[No Changes Needed]
    AE -->|&nbsp;No&nbsp;| AH

    AB --> AI{More Scenarios?}
    AG --> AI
    AH --> AI

    AI -->|&nbsp;Yes&nbsp;| Z
    AI -->|&nbsp;No&nbsp;| AD[Report Hash Mismatches<br/>Warning or Error in validate mode]

    AD --> AJ{Changes Needed?}

    AJ -->|&nbsp;No&nbsp;| NEXT
    AJ -->|&nbsp;Yes&nbsp;| AK[Record Generated/Updated Event]

    AK --> AL{Dry Run?}
    AL -->|&nbsp;Yes&nbsp;| AM[Generate Preview<br/>Show new method code]
    AM --> NEXT

    AL -->|&nbsp;No&nbsp;| AN{File Exists?}
    AN -->|&nbsp;No&nbsp;| AO[TestFileWriter::writeNew<br/><code>TestFileWriter.php</code><br/>Create new test class]
    AN -->|&nbsp;Yes&nbsp;| AP[TestFileWriter::update<br/>Insert methods, update providers]

    AO --> NEXT
    AP --> NEXT

    NEXT -->|&nbsp;Yes&nbsp;| I
    NEXT -->|&nbsp;No&nbsp;| AQ[SyncResult::printSummary<br/><code>SyncResult.php</code>]

    AQ --> AR{Validate Mode?}
    AR -->|&nbsp;Yes, has changes&nbsp;| AS[Exit 1<br/>Tests out of sync]
    AR -->|&nbsp;No&nbsp;| AT{Has Errors?}
    AT -->|&nbsp;Yes&nbsp;| AU[Exit 1<br/>Completed with errors]
    AT -->|&nbsp;No&nbsp;| AV[Exit 0<br/>Sync complete]

    style A fill:#e1f5fe
    style C fill:#c8e6c9
    style H fill:#c8e6c9
    style AS fill:#ffcdd2
    style AU fill:#ffcdd2
    style AV fill:#c8e6c9
    style B fill:#fff8e1
    style G fill:#fff8e1
    style M fill:#fff8e1
    style P fill:#fff8e1
    style S fill:#fff8e1
    style U fill:#fff8e1
    style W fill:#fff8e1
    style AA fill:#fff8e1
    style AE fill:#fff8e1
    style AF fill:#fff8e1
    style AI fill:#fff8e1
    style AJ fill:#fff8e1
    style AL fill:#fff8e1
    style AN fill:#fff8e1
    style AR fill:#fff8e1
    style AT fill:#fff8e1
    style NEXT fill:#fff8e1
    style N fill:#ffcdd2
    style AD fill:#fff3e0
    style V fill:#e8f5e9
    style AB fill:#e8f5e9
    style AG fill:#e8f5e9
    style AO fill:#e8f5e9
    style AP fill:#e8f5e9
    style R fill:#e3f2fd
    style R2 fill:#e3f2fd
```
