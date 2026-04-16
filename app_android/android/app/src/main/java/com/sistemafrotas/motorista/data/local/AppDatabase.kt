package com.sistemafrotas.motorista.data.local

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase
import androidx.room.migration.Migration
import androidx.sqlite.db.SupportSQLiteDatabase

@Database(
    entities = [
        RotaEntity::class,
        AbastecimentoEntity::class,
        ChecklistEntity::class,
        GpsPendingEntity::class,
        PendingSyncEntity::class,
    ],
    version = 5,
    exportSchema = false,
)
abstract class AppDatabase : RoomDatabase() {
    abstract fun rotaDao(): RotaDao
    abstract fun abastecimentoDao(): AbastecimentoDao
    abstract fun checklistDao(): ChecklistDao
    abstract fun gpsPendingDao(): GpsPendingDao
    abstract fun pendingSyncDao(): PendingSyncDao

    companion object {
        val MIGRATION_1_2 = object : Migration(1, 2) {
            override fun migrate(db: SupportSQLiteDatabase) {
                db.execSQL(
                    """
                    CREATE TABLE IF NOT EXISTS gps_pending (
                        id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                        veiculo_id INTEGER NOT NULL,
                        motorista_id INTEGER NOT NULL,
                        latitude REAL NOT NULL,
                        longitude REAL NOT NULL,
                        velocidade REAL,
                        data_hora TEXT NOT NULL,
                        created_at INTEGER NOT NULL
                    )
                    """.trimIndent(),
                )
            }
        }

        val MIGRATION_2_3 = object : Migration(2, 3) {
            override fun migrate(db: SupportSQLiteDatabase) {
                db.execSQL("ALTER TABLE gps_pending ADD COLUMN bateria_pct INTEGER")
            }
        }

        val MIGRATION_3_4 = object : Migration(3, 4) {
            override fun migrate(db: SupportSQLiteDatabase) {
                db.execSQL("ALTER TABLE gps_pending ADD COLUMN accuracy_metros REAL")
                db.execSQL("ALTER TABLE gps_pending ADD COLUMN provider TEXT")
                db.execSQL("ALTER TABLE gps_pending ADD COLUMN location_mock INTEGER")
            }
        }

        val MIGRATION_4_5 = object : Migration(4, 5) {
            override fun migrate(db: SupportSQLiteDatabase) {
                db.execSQL(
                    """
                    CREATE TABLE IF NOT EXISTS pending_sync (
                        id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                        operation TEXT NOT NULL,
                        payload_json TEXT NOT NULL,
                        created_at INTEGER NOT NULL,
                        retry_count INTEGER NOT NULL,
                        last_error TEXT
                    )
                    """.trimIndent(),
                )
            }
        }
    }
}

object DatabaseProvider {
    @Volatile
    private var INSTANCE: AppDatabase? = null

    fun get(context: Context): AppDatabase {
        return INSTANCE ?: synchronized(this) {
            INSTANCE ?: Room.databaseBuilder(
                context.applicationContext,
                AppDatabase::class.java,
                "sistema_frotas_db",
            )
                .addMigrations(
                    AppDatabase.MIGRATION_1_2,
                    AppDatabase.MIGRATION_2_3,
                    AppDatabase.MIGRATION_3_4,
                    AppDatabase.MIGRATION_4_5,
                )
                .build()
                .also { INSTANCE = it }
        }
    }
}
