package com.sistemafrotas.motorista.data.local;

import androidx.annotation.NonNull;
import androidx.room.DatabaseConfiguration;
import androidx.room.InvalidationTracker;
import androidx.room.RoomDatabase;
import androidx.room.RoomOpenHelper;
import androidx.room.migration.AutoMigrationSpec;
import androidx.room.migration.Migration;
import androidx.room.util.DBUtil;
import androidx.room.util.TableInfo;
import androidx.sqlite.db.SupportSQLiteDatabase;
import androidx.sqlite.db.SupportSQLiteOpenHelper;
import java.lang.Class;
import java.lang.Override;
import java.lang.String;
import java.lang.SuppressWarnings;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.HashSet;
import java.util.List;
import java.util.Map;
import java.util.Set;
import javax.annotation.processing.Generated;

@Generated("androidx.room.RoomProcessor")
@SuppressWarnings({"unchecked", "deprecation"})
public final class AppDatabase_Impl extends AppDatabase {
  private volatile RotaDao _rotaDao;

  private volatile AbastecimentoDao _abastecimentoDao;

  private volatile ChecklistDao _checklistDao;

  private volatile GpsPendingDao _gpsPendingDao;

  private volatile PendingSyncDao _pendingSyncDao;

  @Override
  @NonNull
  protected SupportSQLiteOpenHelper createOpenHelper(@NonNull final DatabaseConfiguration config) {
    final SupportSQLiteOpenHelper.Callback _openCallback = new RoomOpenHelper(config, new RoomOpenHelper.Delegate(5) {
      @Override
      public void createAllTables(@NonNull final SupportSQLiteDatabase db) {
        db.execSQL("CREATE TABLE IF NOT EXISTS `rotas_cache` (`id` INTEGER NOT NULL, `veiculoId` INTEGER, `dataRota` TEXT, `dataSaida` TEXT, `cidadeOrigemNome` TEXT, `cidadeDestinoNome` TEXT, `placa` TEXT, `status` TEXT, `distanciaKm` REAL, `syncedAt` INTEGER NOT NULL, PRIMARY KEY(`id`))");
        db.execSQL("CREATE TABLE IF NOT EXISTS `abastecimentos_cache` (`id` INTEGER NOT NULL, `veiculoId` INTEGER, `dataAbastecimento` TEXT, `placa` TEXT, `litros` REAL, `valorTotal` REAL, `status` TEXT, `syncedAt` INTEGER NOT NULL, PRIMARY KEY(`id`))");
        db.execSQL("CREATE TABLE IF NOT EXISTS `checklists_cache` (`id` INTEGER NOT NULL, `rotaId` INTEGER, `veiculoId` INTEGER, `dataChecklist` TEXT, `placa` TEXT, `cidadeOrigemNome` TEXT, `cidadeDestinoNome` TEXT, `syncedAt` INTEGER NOT NULL, PRIMARY KEY(`id`))");
        db.execSQL("CREATE TABLE IF NOT EXISTS `gps_pending` (`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, `veiculo_id` INTEGER NOT NULL, `motorista_id` INTEGER NOT NULL, `latitude` REAL NOT NULL, `longitude` REAL NOT NULL, `velocidade` REAL, `bateria_pct` INTEGER, `accuracy_metros` REAL, `provider` TEXT, `location_mock` INTEGER, `data_hora` TEXT NOT NULL, `created_at` INTEGER NOT NULL)");
        db.execSQL("CREATE TABLE IF NOT EXISTS `pending_sync` (`id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, `operation` TEXT NOT NULL, `payload_json` TEXT NOT NULL, `created_at` INTEGER NOT NULL, `retry_count` INTEGER NOT NULL, `last_error` TEXT)");
        db.execSQL("CREATE TABLE IF NOT EXISTS room_master_table (id INTEGER PRIMARY KEY,identity_hash TEXT)");
        db.execSQL("INSERT OR REPLACE INTO room_master_table (id,identity_hash) VALUES(42, '49e9bee6277517dde0dcde087cb3158f')");
      }

      @Override
      public void dropAllTables(@NonNull final SupportSQLiteDatabase db) {
        db.execSQL("DROP TABLE IF EXISTS `rotas_cache`");
        db.execSQL("DROP TABLE IF EXISTS `abastecimentos_cache`");
        db.execSQL("DROP TABLE IF EXISTS `checklists_cache`");
        db.execSQL("DROP TABLE IF EXISTS `gps_pending`");
        db.execSQL("DROP TABLE IF EXISTS `pending_sync`");
        final List<? extends RoomDatabase.Callback> _callbacks = mCallbacks;
        if (_callbacks != null) {
          for (RoomDatabase.Callback _callback : _callbacks) {
            _callback.onDestructiveMigration(db);
          }
        }
      }

      @Override
      public void onCreate(@NonNull final SupportSQLiteDatabase db) {
        final List<? extends RoomDatabase.Callback> _callbacks = mCallbacks;
        if (_callbacks != null) {
          for (RoomDatabase.Callback _callback : _callbacks) {
            _callback.onCreate(db);
          }
        }
      }

      @Override
      public void onOpen(@NonNull final SupportSQLiteDatabase db) {
        mDatabase = db;
        internalInitInvalidationTracker(db);
        final List<? extends RoomDatabase.Callback> _callbacks = mCallbacks;
        if (_callbacks != null) {
          for (RoomDatabase.Callback _callback : _callbacks) {
            _callback.onOpen(db);
          }
        }
      }

      @Override
      public void onPreMigrate(@NonNull final SupportSQLiteDatabase db) {
        DBUtil.dropFtsSyncTriggers(db);
      }

      @Override
      public void onPostMigrate(@NonNull final SupportSQLiteDatabase db) {
      }

      @Override
      @NonNull
      public RoomOpenHelper.ValidationResult onValidateSchema(
          @NonNull final SupportSQLiteDatabase db) {
        final HashMap<String, TableInfo.Column> _columnsRotasCache = new HashMap<String, TableInfo.Column>(10);
        _columnsRotasCache.put("id", new TableInfo.Column("id", "INTEGER", true, 1, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsRotasCache.put("veiculoId", new TableInfo.Column("veiculoId", "INTEGER", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsRotasCache.put("dataRota", new TableInfo.Column("dataRota", "TEXT", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsRotasCache.put("dataSaida", new TableInfo.Column("dataSaida", "TEXT", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsRotasCache.put("cidadeOrigemNome", new TableInfo.Column("cidadeOrigemNome", "TEXT", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsRotasCache.put("cidadeDestinoNome", new TableInfo.Column("cidadeDestinoNome", "TEXT", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsRotasCache.put("placa", new TableInfo.Column("placa", "TEXT", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsRotasCache.put("status", new TableInfo.Column("status", "TEXT", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsRotasCache.put("distanciaKm", new TableInfo.Column("distanciaKm", "REAL", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsRotasCache.put("syncedAt", new TableInfo.Column("syncedAt", "INTEGER", true, 0, null, TableInfo.CREATED_FROM_ENTITY));
        final HashSet<TableInfo.ForeignKey> _foreignKeysRotasCache = new HashSet<TableInfo.ForeignKey>(0);
        final HashSet<TableInfo.Index> _indicesRotasCache = new HashSet<TableInfo.Index>(0);
        final TableInfo _infoRotasCache = new TableInfo("rotas_cache", _columnsRotasCache, _foreignKeysRotasCache, _indicesRotasCache);
        final TableInfo _existingRotasCache = TableInfo.read(db, "rotas_cache");
        if (!_infoRotasCache.equals(_existingRotasCache)) {
          return new RoomOpenHelper.ValidationResult(false, "rotas_cache(com.sistemafrotas.motorista.data.local.RotaEntity).\n"
                  + " Expected:\n" + _infoRotasCache + "\n"
                  + " Found:\n" + _existingRotasCache);
        }
        final HashMap<String, TableInfo.Column> _columnsAbastecimentosCache = new HashMap<String, TableInfo.Column>(8);
        _columnsAbastecimentosCache.put("id", new TableInfo.Column("id", "INTEGER", true, 1, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsAbastecimentosCache.put("veiculoId", new TableInfo.Column("veiculoId", "INTEGER", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsAbastecimentosCache.put("dataAbastecimento", new TableInfo.Column("dataAbastecimento", "TEXT", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsAbastecimentosCache.put("placa", new TableInfo.Column("placa", "TEXT", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsAbastecimentosCache.put("litros", new TableInfo.Column("litros", "REAL", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsAbastecimentosCache.put("valorTotal", new TableInfo.Column("valorTotal", "REAL", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsAbastecimentosCache.put("status", new TableInfo.Column("status", "TEXT", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsAbastecimentosCache.put("syncedAt", new TableInfo.Column("syncedAt", "INTEGER", true, 0, null, TableInfo.CREATED_FROM_ENTITY));
        final HashSet<TableInfo.ForeignKey> _foreignKeysAbastecimentosCache = new HashSet<TableInfo.ForeignKey>(0);
        final HashSet<TableInfo.Index> _indicesAbastecimentosCache = new HashSet<TableInfo.Index>(0);
        final TableInfo _infoAbastecimentosCache = new TableInfo("abastecimentos_cache", _columnsAbastecimentosCache, _foreignKeysAbastecimentosCache, _indicesAbastecimentosCache);
        final TableInfo _existingAbastecimentosCache = TableInfo.read(db, "abastecimentos_cache");
        if (!_infoAbastecimentosCache.equals(_existingAbastecimentosCache)) {
          return new RoomOpenHelper.ValidationResult(false, "abastecimentos_cache(com.sistemafrotas.motorista.data.local.AbastecimentoEntity).\n"
                  + " Expected:\n" + _infoAbastecimentosCache + "\n"
                  + " Found:\n" + _existingAbastecimentosCache);
        }
        final HashMap<String, TableInfo.Column> _columnsChecklistsCache = new HashMap<String, TableInfo.Column>(8);
        _columnsChecklistsCache.put("id", new TableInfo.Column("id", "INTEGER", true, 1, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsChecklistsCache.put("rotaId", new TableInfo.Column("rotaId", "INTEGER", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsChecklistsCache.put("veiculoId", new TableInfo.Column("veiculoId", "INTEGER", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsChecklistsCache.put("dataChecklist", new TableInfo.Column("dataChecklist", "TEXT", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsChecklistsCache.put("placa", new TableInfo.Column("placa", "TEXT", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsChecklistsCache.put("cidadeOrigemNome", new TableInfo.Column("cidadeOrigemNome", "TEXT", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsChecklistsCache.put("cidadeDestinoNome", new TableInfo.Column("cidadeDestinoNome", "TEXT", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsChecklistsCache.put("syncedAt", new TableInfo.Column("syncedAt", "INTEGER", true, 0, null, TableInfo.CREATED_FROM_ENTITY));
        final HashSet<TableInfo.ForeignKey> _foreignKeysChecklistsCache = new HashSet<TableInfo.ForeignKey>(0);
        final HashSet<TableInfo.Index> _indicesChecklistsCache = new HashSet<TableInfo.Index>(0);
        final TableInfo _infoChecklistsCache = new TableInfo("checklists_cache", _columnsChecklistsCache, _foreignKeysChecklistsCache, _indicesChecklistsCache);
        final TableInfo _existingChecklistsCache = TableInfo.read(db, "checklists_cache");
        if (!_infoChecklistsCache.equals(_existingChecklistsCache)) {
          return new RoomOpenHelper.ValidationResult(false, "checklists_cache(com.sistemafrotas.motorista.data.local.ChecklistEntity).\n"
                  + " Expected:\n" + _infoChecklistsCache + "\n"
                  + " Found:\n" + _existingChecklistsCache);
        }
        final HashMap<String, TableInfo.Column> _columnsGpsPending = new HashMap<String, TableInfo.Column>(12);
        _columnsGpsPending.put("id", new TableInfo.Column("id", "INTEGER", true, 1, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsGpsPending.put("veiculo_id", new TableInfo.Column("veiculo_id", "INTEGER", true, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsGpsPending.put("motorista_id", new TableInfo.Column("motorista_id", "INTEGER", true, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsGpsPending.put("latitude", new TableInfo.Column("latitude", "REAL", true, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsGpsPending.put("longitude", new TableInfo.Column("longitude", "REAL", true, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsGpsPending.put("velocidade", new TableInfo.Column("velocidade", "REAL", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsGpsPending.put("bateria_pct", new TableInfo.Column("bateria_pct", "INTEGER", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsGpsPending.put("accuracy_metros", new TableInfo.Column("accuracy_metros", "REAL", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsGpsPending.put("provider", new TableInfo.Column("provider", "TEXT", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsGpsPending.put("location_mock", new TableInfo.Column("location_mock", "INTEGER", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsGpsPending.put("data_hora", new TableInfo.Column("data_hora", "TEXT", true, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsGpsPending.put("created_at", new TableInfo.Column("created_at", "INTEGER", true, 0, null, TableInfo.CREATED_FROM_ENTITY));
        final HashSet<TableInfo.ForeignKey> _foreignKeysGpsPending = new HashSet<TableInfo.ForeignKey>(0);
        final HashSet<TableInfo.Index> _indicesGpsPending = new HashSet<TableInfo.Index>(0);
        final TableInfo _infoGpsPending = new TableInfo("gps_pending", _columnsGpsPending, _foreignKeysGpsPending, _indicesGpsPending);
        final TableInfo _existingGpsPending = TableInfo.read(db, "gps_pending");
        if (!_infoGpsPending.equals(_existingGpsPending)) {
          return new RoomOpenHelper.ValidationResult(false, "gps_pending(com.sistemafrotas.motorista.data.local.GpsPendingEntity).\n"
                  + " Expected:\n" + _infoGpsPending + "\n"
                  + " Found:\n" + _existingGpsPending);
        }
        final HashMap<String, TableInfo.Column> _columnsPendingSync = new HashMap<String, TableInfo.Column>(6);
        _columnsPendingSync.put("id", new TableInfo.Column("id", "INTEGER", true, 1, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsPendingSync.put("operation", new TableInfo.Column("operation", "TEXT", true, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsPendingSync.put("payload_json", new TableInfo.Column("payload_json", "TEXT", true, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsPendingSync.put("created_at", new TableInfo.Column("created_at", "INTEGER", true, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsPendingSync.put("retry_count", new TableInfo.Column("retry_count", "INTEGER", true, 0, null, TableInfo.CREATED_FROM_ENTITY));
        _columnsPendingSync.put("last_error", new TableInfo.Column("last_error", "TEXT", false, 0, null, TableInfo.CREATED_FROM_ENTITY));
        final HashSet<TableInfo.ForeignKey> _foreignKeysPendingSync = new HashSet<TableInfo.ForeignKey>(0);
        final HashSet<TableInfo.Index> _indicesPendingSync = new HashSet<TableInfo.Index>(0);
        final TableInfo _infoPendingSync = new TableInfo("pending_sync", _columnsPendingSync, _foreignKeysPendingSync, _indicesPendingSync);
        final TableInfo _existingPendingSync = TableInfo.read(db, "pending_sync");
        if (!_infoPendingSync.equals(_existingPendingSync)) {
          return new RoomOpenHelper.ValidationResult(false, "pending_sync(com.sistemafrotas.motorista.data.local.PendingSyncEntity).\n"
                  + " Expected:\n" + _infoPendingSync + "\n"
                  + " Found:\n" + _existingPendingSync);
        }
        return new RoomOpenHelper.ValidationResult(true, null);
      }
    }, "49e9bee6277517dde0dcde087cb3158f", "622fb88255bbc49bd276bb73827121ea");
    final SupportSQLiteOpenHelper.Configuration _sqliteConfig = SupportSQLiteOpenHelper.Configuration.builder(config.context).name(config.name).callback(_openCallback).build();
    final SupportSQLiteOpenHelper _helper = config.sqliteOpenHelperFactory.create(_sqliteConfig);
    return _helper;
  }

  @Override
  @NonNull
  protected InvalidationTracker createInvalidationTracker() {
    final HashMap<String, String> _shadowTablesMap = new HashMap<String, String>(0);
    final HashMap<String, Set<String>> _viewTables = new HashMap<String, Set<String>>(0);
    return new InvalidationTracker(this, _shadowTablesMap, _viewTables, "rotas_cache","abastecimentos_cache","checklists_cache","gps_pending","pending_sync");
  }

  @Override
  public void clearAllTables() {
    super.assertNotMainThread();
    final SupportSQLiteDatabase _db = super.getOpenHelper().getWritableDatabase();
    try {
      super.beginTransaction();
      _db.execSQL("DELETE FROM `rotas_cache`");
      _db.execSQL("DELETE FROM `abastecimentos_cache`");
      _db.execSQL("DELETE FROM `checklists_cache`");
      _db.execSQL("DELETE FROM `gps_pending`");
      _db.execSQL("DELETE FROM `pending_sync`");
      super.setTransactionSuccessful();
    } finally {
      super.endTransaction();
      _db.query("PRAGMA wal_checkpoint(FULL)").close();
      if (!_db.inTransaction()) {
        _db.execSQL("VACUUM");
      }
    }
  }

  @Override
  @NonNull
  protected Map<Class<?>, List<Class<?>>> getRequiredTypeConverters() {
    final HashMap<Class<?>, List<Class<?>>> _typeConvertersMap = new HashMap<Class<?>, List<Class<?>>>();
    _typeConvertersMap.put(RotaDao.class, RotaDao_Impl.getRequiredConverters());
    _typeConvertersMap.put(AbastecimentoDao.class, AbastecimentoDao_Impl.getRequiredConverters());
    _typeConvertersMap.put(ChecklistDao.class, ChecklistDao_Impl.getRequiredConverters());
    _typeConvertersMap.put(GpsPendingDao.class, GpsPendingDao_Impl.getRequiredConverters());
    _typeConvertersMap.put(PendingSyncDao.class, PendingSyncDao_Impl.getRequiredConverters());
    return _typeConvertersMap;
  }

  @Override
  @NonNull
  public Set<Class<? extends AutoMigrationSpec>> getRequiredAutoMigrationSpecs() {
    final HashSet<Class<? extends AutoMigrationSpec>> _autoMigrationSpecsSet = new HashSet<Class<? extends AutoMigrationSpec>>();
    return _autoMigrationSpecsSet;
  }

  @Override
  @NonNull
  public List<Migration> getAutoMigrations(
      @NonNull final Map<Class<? extends AutoMigrationSpec>, AutoMigrationSpec> autoMigrationSpecs) {
    final List<Migration> _autoMigrations = new ArrayList<Migration>();
    return _autoMigrations;
  }

  @Override
  public RotaDao rotaDao() {
    if (_rotaDao != null) {
      return _rotaDao;
    } else {
      synchronized(this) {
        if(_rotaDao == null) {
          _rotaDao = new RotaDao_Impl(this);
        }
        return _rotaDao;
      }
    }
  }

  @Override
  public AbastecimentoDao abastecimentoDao() {
    if (_abastecimentoDao != null) {
      return _abastecimentoDao;
    } else {
      synchronized(this) {
        if(_abastecimentoDao == null) {
          _abastecimentoDao = new AbastecimentoDao_Impl(this);
        }
        return _abastecimentoDao;
      }
    }
  }

  @Override
  public ChecklistDao checklistDao() {
    if (_checklistDao != null) {
      return _checklistDao;
    } else {
      synchronized(this) {
        if(_checklistDao == null) {
          _checklistDao = new ChecklistDao_Impl(this);
        }
        return _checklistDao;
      }
    }
  }

  @Override
  public GpsPendingDao gpsPendingDao() {
    if (_gpsPendingDao != null) {
      return _gpsPendingDao;
    } else {
      synchronized(this) {
        if(_gpsPendingDao == null) {
          _gpsPendingDao = new GpsPendingDao_Impl(this);
        }
        return _gpsPendingDao;
      }
    }
  }

  @Override
  public PendingSyncDao pendingSyncDao() {
    if (_pendingSyncDao != null) {
      return _pendingSyncDao;
    } else {
      synchronized(this) {
        if(_pendingSyncDao == null) {
          _pendingSyncDao = new PendingSyncDao_Impl(this);
        }
        return _pendingSyncDao;
      }
    }
  }
}
